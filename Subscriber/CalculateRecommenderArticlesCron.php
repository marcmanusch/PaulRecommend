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

        $config = $this->container->get('shopware.plugin.config_reader')->getByPluginName('PaulRecommend');


        // get plugin settings
        $active = $config['active'];
        $support_config = $config['support'];
        $confidence_config = $config['confidence'];
        $date_from = $config['date'];
        $otherData = $config['otherData'];
        $filterTransactions = $config['filter'];

        // Array mit ausgeschlossenen Artikeln
        $filterTransactions = explode(",", $filterTransactions);

        //Ersetze Komma mit Punkt!
        try {
            $support_config = str_replace(',', '.', $support_config);
            $confidence_config = str_replace(',', '.', $confidence_config);

        } catch (\Exception $e){}


        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->container->get('dbal_connection');

        if (!$otherData) {

            $builder = $this->getAllTransactions($date_from);
            $stmt = $builder->execute();
            $transactionDataSet = $stmt->fetchAll();

            $arr = array();

            foreach ($transactionDataSet as $key => $item) {
                $arr[$item['ordernumber']][$key] = $item;
            }

            ksort($arr, SORT_NUMERIC);

            /**
             * Erstelle Array, dass als Key die Ordernumber hat und als Values die Articleordernumber.
             */
            $clearedTransactions = [];
            foreach ($arr as $key => $transaction) {
               foreach ($transaction as $itemInTransaction) {
                   $clearedTransactions[$key][] = $itemInTransaction['articleordernumber'];
               }
            }
            // Lösche alle Artikel aus dem Array, die nicht zugeordnet werden können
            unset($clearedTransactions[0]);

            /*echo '<pre>';
            echo var_dump($transactions);
            echo '</pre>';*/

            // Entferne Filterartikel aus Transaktionen
            foreach ($clearedTransactions as $key => $data) {
                foreach ($data as $article) {
                    foreach ($filterTransactions as $filteritem) {
                        if($article == $filteritem) {
                            $fields = array_flip($clearedTransactions[$key]);
                            unset($fields[$filteritem]);
                            $clearedTransactions[$key] = array_flip($fields);
                        }
                    }
                }
            }

            //Lösche alle Transaktionen mit nur einem Artikel
            $transactions = [];
            foreach ($clearedTransactions as $data) {
                if(count($data) > 1){
                    $transactions[] = $data;
                }
            }

            $samples = $transactions;
            $labels = [];
            $associator = new Apriori($support_config, $confidence_config);
            $associator->train($samples, $labels);
            $rules = $associator->getRules();

            $articles = $this->getAllArticles();

            foreach ($articles as $article) {
                $ordernumber = $article['ordernumber'];
                $this->saveAssociationRules($rules, $ordernumber);
            }

            /**
             * TEST Dump
             */
           /* if($samples) {
                echo '########## Neuer Artikel ########';
                echo '<pre>';
                echo '----- Samples -----';
                echo '<br>';
                echo var_dump($samples);
                echo '----- Samples ENDE -----';
                echo '<br>';
                echo '<br>';
                echo '----- getRules() -----';
                echo '<br>';
                echo '----- Support:'.$support_config.' -----';
                echo '<br>';
                echo '----- Confidence:'.$confidence_config.' -----';
                echo '<br>';
                echo var_dump($associator->getRules());
                echo '<br>';
                echo '----- getRules() ENDE-----';
                echo '</pre>';
                echo '<br>';
                echo '########## FERTIG ########';
                echo '<br>';
            }*/

        } else {
            /**
             * Hier können weitere Datenquellen eingelesen werden.
             * Die Steuerung erfolgt über die Plugin Config.
             */
        }

    }


    /**
     * @param $order
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    private function getAllTransactions($date_from) {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->container->get('dbal_connection');
        $builder = $connection->createQueryBuilder();
        $builder->select('sod.ordernumber', 'sod.articleordernumber', 'sod.orderID', 'so.ordertime')
            ->from('s_order_details', 'sod')
            ->innerJoin('sod',
                's_order',
                'so',
                'sod.orderID = so.id')
            ->andWhere('so.ordertime >= \'' . $date_from . '\'');
        return $builder;
    }

    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    private function saveAssociationRules($rules, $ordernumber) {

        $resource = \Shopware\Components\Api\Manager::getResource('Article');

        // Array für alle Vorschläge
        $recommendations = [];

        //Filter rules to add to DB
        foreach ($rules as $rule) {

            // Wenn links von der Regel nur ein Artikel steht und dieser Artikel
            // der gesucht ist speichere in DB.
            if(count($rule['antecedent']) == 1 &  $rule['antecedent'][0] == $ordernumber) {
                $recommendations[] = $rule;
            }
        }

        //Sortiere Array nach Confidence
        usort($recommendations, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });


        // Speichere alle möglichen Vorschläge
        try {
            $article = $resource->getOneByNumber($ordernumber);
            $articleDetailsID = $article['mainDetailId'];

            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = $this->container->get('dbal_connection');
            $builder = $connection->createQueryBuilder();
            $builder->update('s_articles_attributes', 'saa')
                ->set('saa.recommend_articles', '?')
                ->setParameter(0, json_encode($recommendations))
                ->where('articledetailsID = \'' . $articleDetailsID . '\'');
            $builder->execute();

        } catch (\Exception $e){}

    }

    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    private function getAllArticles() {

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->container->get('dbal_connection');
        $builder = $connection->createQueryBuilder();
        $builder->select('sad.ordernumber')
            ->from('s_articles_details', 'sad');
        $stmt = $builder->execute();
        return $stmt->fetchAll();
    }

}