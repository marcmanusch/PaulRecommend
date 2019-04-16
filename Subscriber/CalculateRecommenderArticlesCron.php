<?php
/**
 * Created by PhpStorm.
 * User: marc
 * Date: 09.11.18
 * Time: 15:36
 */

namespace PaulRecommend\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Template_Manager;
use Shopware\Components\Plugin\ConfigReader;
use PaulRecommend\Vendor\Apriori;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Class CalculateRecommenderArticlesCron
 * @package PaulRecommend\Subscriber
 *
 * In dieser Klasse werden die vorgeschlagenen Produkte berrechnet.
 * Dieser prozess wird durch einen CRON angestoßen, sodass die Berrechnung NICHT
 * auf Echtzeit-Daten läuft. Hierdurch wird eine hohe Performance erreicht.
 */
class CalculateRecommenderArticlesCron implements SubscriberInterface
{
    /** @var  ContainerInterface */
    private $container;

    /**
     * Frontend contructor.
     * @param ContainerInterface $container
     **/
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'Shopware_CronJob_CalculateRecommenderArticlesCron' => 'CalculateRecommenderArticles'
        ];
    }

    /**
     * @param \Shopware_Components_Cron_CronJob $job
     */
    public function CalculateRecommenderArticles(\Shopware_Components_Cron_CronJob $job)
    {
        //Testausgabe
        echo('YES!');

        $config = $this->container->get('shopware.plugin.config_reader')->getByPluginName('PaulRecommend');


        // get plugin settings
        $active = $config['active'];
        $support_config = $config['support'];
        $confidence_config = $config['confidence'];
        $date_from = $config['date'];
        $otherData = $config['otherData'];


        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->container->get('dbal_connection');

        if (!$otherData) {
            /**
             * Wenn in der Plugin-Config der Haken "Benutze andere Daten" gesetzt wird, wird der ELSE part
             * aufgerufen. Die Daten werden dann aus der DB-Tabelle "apriori_liste" genommen. Diese Daten
             * müssen direkt über die DB gepflegt werden!
             *
             * Die Abfrage aus dem IF part holt sich die Daten der orders aus der SHOPWARE DB.
             */

            // Hole alle Artikelnummern aus dem Shop als Array
            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = $this->container->get('dbal_connection');
            $builder = $connection->createQueryBuilder();
            $builder->select('sad.ordernumber')
                ->from('s_articles_details', 'sad');

            // Führe Anfrage auf der DB aus.
            $stmt = $builder->execute();
            $articles = $stmt->fetchAll();


            // Berechne Algorithmus für ALLE Artikel aus dem Shop!
            foreach ($articles as $article) {
                $builder = $this->getOrdersOfItem($article['ordernumber'], $date_from);
                $this->calculate($builder, $support_config, $confidence_config, $article);
            }


        } else {
            /**
             * Lese daten aus der DB-Tabelle "apriori_liste".
             */
            //$builder = $this->getOtherOrders();
        }


    }

    public function calculate($builder, $support_config, $confidence_config, $article) {

        // Führe Anfrage auf der DB aus.
        $stmt = $builder->execute();
        $ordersDataSet = $stmt->fetchAll();

        // Extrahiere alle Bestellnummern in den der aktuelle Artikel bestellt wurde.
        $arrayOfOrderIDs = [];
        foreach ($ordersDataSet as $order) {
            $arrayOfOrderIDs[] = $order['ordernumber'];
        }

        // Erstelle Array mit Artikelnummern von jeder Bestellung
        $arrayItemsInOrder = [];
        foreach ($arrayOfOrderIDs as $key => $orderID) {

            $builder = $this->getOrdernumbersOfOrder($orderID);


            $stmt = $builder->execute();
            $arrayItemsInOrder[$orderID] = $stmt->fetchAll();
        }

        $arrayOrdernumbersInOrder = [];
        foreach ($arrayItemsInOrder as $key => $item) {
            foreach ($item as $data) {
                $arrayOrdernumbersInOrder[$key][] = $data['articleordernumber'];
            }
        }

        // Lösche falsche Artikel auf dem Sample Array mit orderID 0
        unset($arrayOrdernumbersInOrder['0']);

        $samples = array_values($arrayOrdernumbersInOrder);
        $labels = [];
        $associator = new Apriori($support_config, $confidence_config);
        $associator->train($samples, $labels);

        // Apriori Array
        $apriori = $associator->apriori();

        $aprioriArticles = array();

        // !!!! Begrenze Anzahl auf 6 Artikel !!!!
        $anzahlAprioriAusgabe = 0;
        if(count($apriori) >= 7) {
            $anzahlAprioriAusgabe = 7;
        } else {
            $anzahlAprioriAusgabe = count($apriori);
        }

        // Hole Artikelinfomrationen für Artikel aus dem Apriori-Algorithmus
        foreach ($apriori[($anzahlAprioriAusgabe) - 1][0] as $article_apriori) {

            // Wenn Artikel inaktiv etc überspringen...
            try {
                /*$resource = \Shopware\Components\Api\Manager::getResource('Article');
                $article = $resource->getOneByNumber($article_apriori);
                $aprioriArticles[] = $article;*/
                $aprioriArticles[] = $article_apriori;
            } catch (\Exception $e) {

            }
        }

        // Sortiere Array so, dass das aktuelle Produkt immer als erstes im Array steht.
        foreach ($aprioriArticles as $key => $item) {
            if($item['ordernumber'] === $article) {
                unset($aprioriArticles[$key]);
            }
        }
        array_unshift($aprioriArticles , $article);

        //speichere die Ordernumbers im angelegten Attribute zum Artikel
        $builder = $this->saveRecommendArticles($aprioriArticles);
        $builder->execute();

    }

    public function saveRecommendArticles($aprioriArticles) {

        //lösche aktuellen artikel von position 0
        // & speichere in neuer variable
        $ordernumber = $aprioriArticles[0];
        unset($aprioriArticles[0]);

        $resource = \Shopware\Components\Api\Manager::getResource('Article');
        $article = $resource->getOneByNumber($ordernumber);
        $articleDetailsID = $article['mainDetailId'];

        /*echo'<pre>';
        var_dump($articleDetailsID);
        echo'<pre>';
        echo'###### Nächster Artikel ######';
        echo '<br>';*/

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->container->get('dbal_connection');
        $builder = $connection->createQueryBuilder();
        $builder->update('s_articles_attributes', 'saa')
            ->set('saa.recommend_articles', '?')
            ->setParameter(0, json_encode($aprioriArticles))
            ->where('articledetailsID = \'' . $articleDetailsID . '\'');
        return $builder;
    }

    /**
     * Diese Funktion liefert die Bestellnummern, in denen der aktuelle Artikel gekauft wurde.
     * Die Daten werden für die weitere Verarbeitung benötigt.
     * @param $ordernumber
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function getOrdersOfItem($ordernumber, $date_from)
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->container->get('dbal_connection');
        $builder = $connection->createQueryBuilder();
        $builder->select('sod.ordernumber', 'sod.articleordernumber', 'sod.orderID', 'so.ordertime')
            ->from('s_order_details', 'sod')
            ->innerJoin('sod',
                's_order',
                'so',
                'sod.orderID = so.id')
            ->where('sod.articleordernumber = \'' . $ordernumber . '\'')
            ->andWhere('so.ordertime >= \'' . $date_from . '\'');
        return $builder;
    }

    /**
     * Diese Funktion gibt die Artikel einer Bestellung aus.
     * Die Bestellung, die an diese Funktion übergeben wird, beinhaltet das aktuell aufgerufene Produkt.
     * Dieses Produkt dient als "Basis" für die Generierung des Apriori Datasets.
     * @param $order
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    private function getOrdernumbersOfOrder($order)
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->container->get('dbal_connection');
        $builder = $connection->createQueryBuilder();
        $builder->select('ordernumber', 'articleordernumber')
            ->from('s_order_details', 'sod')
            ->where('sod.ordernumber = \'' . $order . '\'');
        return $builder;
    }

}