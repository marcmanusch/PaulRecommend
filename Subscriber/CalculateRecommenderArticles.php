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

class CalculateRecommenderArticles implements SubscriberInterface
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'Shopware_CronJob_CalculateRecommenderArticles' => 'CalculateRecommenderArticles'
        ];
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     */
    public function CalculateRecommenderArticles(\Shopware_Components_Cron_CronJob $job)
    {
        //Testausgabe
        echo('YES!');
    }

}