<?php
/**
 * Created by PhpStorm.
 * User: marc
 * Date: 09.11.18
 * Time: 15:36
 */

namespace PaulRecommend\Subscriber;

use Enlight\Event\SubscriberInterface;
use Shopware\Components\Plugin\ConfigReader;
use PaulRecommend\Vendor\ApiClient\PlentyApiClient;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Class GetPlentyMarketsOrdersCron
 * @package PaulRecommend\Subscriber
 *
 * Diese Klasse importiert Bestellungen aus dem PlentyMarkets ERP-ystem über die REST-API.
 */
class GetPlentyMarketsOrdersCron implements SubscriberInterface
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
            'Shopware_CronJob_GetPlentyMarketsOrdersCron' => 'GetPlentyMarketsOrders'
        ];
    }

    /**
     * @param \Shopware_Components_Cron_CronJob $job
     */
    public function GetPlentyMarketsOrders(\Shopware_Components_Cron_CronJob $job)
    {

        $start = $this->startTimer();
        $timer_end = 0;

        $config = $this->container->get('shopware.plugin.config_reader')->getByPluginName('PaulRecommend');

        // get plugin settings
        $otherData = $config['otherData'];
        $date_from = $config['date'] . '+02:00';
        $plentyUsername = $config['api_user'];
        $plentyPassword = $config['api_password'];
        $plentyURL = $config['api_url'];
        $dump = $config['dump'];
        $filter_plenty_sku = $config['filter_plenty_sku'];

        // Array mit ausgeschlossenen Artikeln
        $filterSku = explode(",", $filter_plenty_sku);


        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->container->get('dbal_connection');

        if ($otherData) {

            $this->clearTable();

            // PLENTY API CLIENT
            $client = new PlentyApiClient($plentyUsername, $plentyPassword, $plentyURL);

            // REST path
            $path = "rest/orders";

            $itemsPerPage = 50;

            //FILTER
            $filter = [
                'createdAtFrom' => $date_from,
                'with[]' => 'orderItems.variation'
            ];

            $orders = (array)json_decode($client->call('GET', $path, $filter), true);

            //Reursiv call to get all results (50) each call
            $amountOfResults = $orders['totalsCount'];
            $neededCalls = (int)ceil($amountOfResults / $itemsPerPage);

            $transactionArray = [];
            $orders = [];
            $actualCall = 1;
            $counter = 0;

            while ($neededCalls >= $actualCall) {
                $filter = [
                    'createdAtFrom' => $date_from,
                    'itemsPerpage' => $itemsPerPage,
                    'page' => $actualCall,
                    'with[]' => 'orderItems.variation'
                ];

                // PLENTY API CLIENT
                $client = new PlentyApiClient($plentyUsername, $plentyPassword, $plentyURL);
                $orders = (array)json_decode($client->call('GET', $path, $filter), true);

                foreach ($orders['entries'] as $order) {

                    foreach ($order['orderItems'] as $orderItem) {

                        if($orderItem['variation']['bundleType'] == 'bundle'
                            || $orderItem['variation']['bundleType'] == ''
                            || $orderItem['variation']['bundleType'] == 'bundle_item' && empty($orderItem['references']))
                        {

                            $product = $orderItem['variation']['number'];

                            // Entferne Filterartikel aus Transaktionen
                            foreach ($filterSku as $filteritem) {
                                if(stripos($product, $filteritem) == true) {
                                    $product = str_replace($filteritem, '', $product);
                                }

                            }
                            // Save to DB
                            $this->saveTransaction($order['id'], $product);

                            if($dump) {
                                $transactionArray[$counter][$order['id']][] = $product;
                            }

                        }
                    }
                    $counter++;
                }

                $actualCall++;
            }

            if($dump) {
                echo '<pre>';
                    print_r($transactionArray);
                echo '</pre>';
            }

        }

        return 'Laufzeit: ' . $timer_end;

    }


    /**
     * @param $order
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    private function saveTransaction($orderID, $products) {

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->container->get('dbal_connection');
        $builder = $connection->createQueryBuilder();
        $builder
            ->insert('s_plugin_plentymarkets_orders')
            ->values(
                array(
                    'orderID' => '?',
                    'products' => '?'
                )
            )
            ->setParameter(0, $orderID)
            ->setParameter(1, $products)
        ;
        return $builder->execute();
    }

    public function clearTable() {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->container->get('dbal_connection');
        $builder = $connection->createQueryBuilder();
        $builder->delete('s_plugin_plentymarkets_orders');
        return $builder->execute();
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