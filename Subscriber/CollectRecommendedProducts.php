<?php
/**
 * Created by PhpStorm.
 * User: marc
 * Date: 10.11.18
 * Time: 16:35
 */

namespace PaulRecommend\Subscriber;

use Enlight\Event\SubscriberInterface;
use PaulRecommend\Vendor\Apriori;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CollectRecommendedProducts implements SubscriberInterface
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
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Detail' => 'onPostDispatchDetail',
        ];
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     */
    public function onPostDispatchDetail(\Enlight_Event_EventArgs $args)
    {
        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->get('subject');
        $view = $controller->View();
        $view->addTemplateDir($this->pluginDirectory . '/Resources/Views');
        $config = $this->container->get('shopware.plugin.config_reader')->getByPluginName('PaulRecommend');

        // get plugin settings
        $active = $config['active'];
        $support_config = $config['support'];
        $confidence_config = $config['confidence'];
        $date_from = $config['date'];
        $otherData = $config['otherData'];

        // Hole die aktuelle ordernumber (Bestellnummer / MPN) aus der aktuellen View.
        $sArticle = $view->getAssign('sArticle');
        $ordernumber = $sArticle['ordernumber'];

        // Mit der ordernumber wird nun der APRIORI-Algorithmus aufgerufen um die passenden Produkte zu finden.

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->container->get('dbal_connection');

        if(!$otherData) {
            /**
             * Wenn in der Plugin-Config der Haken "Benutze andere Daten" gesetzt wird, wird der ELSE part
             * aufgerufen. Die Daten werden dann aus der DB-Tabelle "apriori_liste" genommen. Diese Daten
             * müssen direkt über die DB gepflegt werden!
             *
             * Die Abfrage aus dem IF part holt sich die Daten der orders aus der SHOPWARE DB.
             */

            $builder = $this->getOrdersOfItem($ordernumber, $date_from);

        } else {
            /**
             * Lese daten aus der DB-Tabelle "apriori_liste".
             */
            $builder = $this->getOtherOrders($ordernumber);
        }

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

            if(!$otherData) {
                /**
                 * Die Abfrage aus dem IF part holt sich die Daten der orders aus der SHOPWARE DB.
                 */

                $builder = $this->getOrdernumbersOfOrder($orderID);

            } else {
                /**
                 * Lese daten aus der DB-Tabelle "apriori_liste".
                 */
                $builder = $this->getOrdernumbersOfOtherOrder($orderID);
            }


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

        //DEVELOP Ausgabe
        $view->assign('develop', $apriori);
        //DEVELOP Ausgabe ENDE

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
                $articleModule = Shopware()->Modules()->Articles();
                $articleID = $articleModule->sGetArticleIdByOrderNumber($article_apriori);
                $article = $articleModule->sGetArticleById($articleID);
                $aprioriArticles[] = $article;
            } catch (\Exception $e) {

            }
        }

        // Sortiere Array so, dass das aktuelle Produkt immer als erstes im Array steht.
        foreach ($aprioriArticles as $key => $item) {
            if($item['ordernumber'] === $ordernumber) {
                unset($aprioriArticles[$key]);
            }
        }
        array_unshift($aprioriArticles , $sArticle);


        // Übergebe Wete an View
        //$view->assign('apriori', $apriori);
        $view->assign('aprioriArticles', $aprioriArticles);



    }

    /**
     * Diese Funktion liefert die Bestellnummern, in denen der aktuelle Artikel gekauft wurde.
     * Die Daten werden für die weitere Verarbeitung benötigt.
     * @param $ordernumber
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    private function getOrdersOfItem($ordernumber, $date_from)
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
     * Wie Funktion "getOrdersOfItem" nur mit Daten aus der DB-Tabelle "apriori_liste"
     * @param $ordernumber
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    private function getOtherOrders($ordernumber)
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->container->get('dbal_connection');
        $builder = $connection->createQueryBuilder();
        $builder->select('*')
            ->from('apriori_liste', 'al')
            ->where('al.articleordernumber = \'' . $ordernumber . '\'');
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


    private function getOrdernumbersOfOtherOrder($order)
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->container->get('dbal_connection');
        $builder = $connection->createQueryBuilder();
        $builder->select('ordernumber', 'articleordernumber')
            ->from('apriori_liste', 'al')
            ->where('al.ordernumber = \'' . $order . '\'');
        return $builder;
    }


}