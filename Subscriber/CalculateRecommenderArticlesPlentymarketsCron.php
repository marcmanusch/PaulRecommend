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
use PaulRecommend\Vendor\ApiClient\PlentyApiClient;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Class CalculateRecommenderArticlesPlentymarketsCron
 * @package PaulRecommend\Subscriber
 */
class CalculateRecommenderArticlesPlentymarketsCron implements SubscriberInterface
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
            'Shopware_CronJob_CalculateRecommenderArticlesPlentymarketsCron' => 'CalculateRecommenderArticles'
        ];
    }

    /**
     * @param \Shopware_Components_Cron_CronJob $job
     */
    public function CalculateRecommenderArticles(\Shopware_Components_Cron_CronJob $job)
    {

        $start = $this->startTimer();
        $timer_end = 0;

        $config = $this->container->get('shopware.plugin.config_reader')->getByPluginName('PaulRecommend');


        // get plugin settings
        $active = $config['active'];
        $support_config = $config['support'];
        $confidence_config = $config['confidence'];
        $date_from = $config['date'];
        $otherData = $config['otherData'];
        $filterTransactions = $config['filter'];
        $dump = $config['dump'];

        // Array mit ausgeschlossenen Artikeln
        $filterTransactions = explode(",", $filterTransactions);

        //Ersetze Komma mit Punkt!
        try {
            $support_config = str_replace(',', '.', $support_config);
            $confidence_config = str_replace(',', '.', $confidence_config);

        } catch (\Exception $e){}


        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->container->get('dbal_connection');

        if ($otherData) {

            $builder = $this->getAllTransactions($date_from);
            $stmt = $builder->execute();
            $transactionDataSet = $stmt->fetchAll();

            $arr = array();

            foreach ($transactionDataSet as $key => $item) {
                $arr[$item['orderID']][$key] = $item;
            }

            ksort($arr, SORT_NUMERIC);

            /**
             * Erstelle Array, dass als Key die Ordernumber hat und als Values die Articleordernumber.
             */
            $clearedTransactions = [];
            foreach ($arr as $key => $transaction) {
                foreach ($transaction as $itemInTransaction) {
                    $clearedTransactions[$key][] = $itemInTransaction['products'];
                }
            }
            // Lösche alle Artikel aus dem Array, die nicht zugeordnet werden können
            unset($clearedTransactions[0]);

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

            // Remove filter vouchers (articles) from the transaction list
            $filterVouchers = $this->getFilterVoucherCodes();

            foreach ($clearedTransactions as $key => $data) {
                foreach ($data as $article) {
                    foreach ($filterVouchers as $filteritem) {
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

            $timer_end = gmdate("H:i:s", $this->stopTimer($start));

            if($dump) {
                /**
                 *  Dump rules and samples
                 */
                if($samples) {
                    echo '<br>';
                    echo '########## Start ##########';
                    echo '<pre>';
                    echo '----- Samples Anzahl: '.count($samples).'-----';
                    echo '<br>';
                    echo var_dump($samples);
                    echo '----- Samples ENDE -----';
                    echo '<br>';
                    echo '<br>';
                    echo '----- getRules() Anzahl: '.count($rules).'-----';
                    echo '<br>';
                    echo '----- Support:'.$support_config.' -----';
                    echo '<br>';
                    echo '----- Confidence:'.$confidence_config.' -----';
                    echo '<br>';
                    echo var_dump($rules);
                    echo '<br>';
                    echo '----- getRules() ENDE-----';
                    echo '</pre>';
                    echo '<br>';
                    echo '########## FERTIG ##########';
                    echo '<br>';
                }
            }


        } else {
            /**
             * Hier können weitere Datenquellen eingelesen werden.
             * Die Steuerung erfolgt über die Plugin Config.
             */
        }

        return 'Laufzeit: ' . $timer_end;

    }


    /**
     * @param $order
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    private function getAllTransactions($date_from) {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->container->get('dbal_connection');
        $builder = $connection->createQueryBuilder();
        $builder->select('pmo.orderID', 'pmo.products')
            ->from('s_plugin_plentymarkets_orders', 'pmo');
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

    /*v
     * This function return an array with all voucher codes of the shop.
     * If an code is deleted, the filter has to be set manually, by adding
     * the code to the filter.
     */
    private function getFilterVoucherCodes () {

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->container->get('dbal_connection');
        $builder = $connection->createQueryBuilder();
        $builder->select('sev.vouchercode')
            ->from('s_emarketing_vouchers', 'sev');
        $stmt = $builder->execute();
        return $stmt->fetchAll();
    }

    private function startTimer()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    private function stopTimer($start, $round=2)
    {
        $endtime = $this->startTimer()-$start;
        $round   = pow(10, $round);
        return round($endtime*$round)/$round;
    }

}