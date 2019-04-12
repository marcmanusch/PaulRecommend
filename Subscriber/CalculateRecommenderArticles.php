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

/**
 * Class CalculateRecommenderArticles
 * @package PaulRecommend\Subscriber
 *
 * In dieser Klasse werden die vorgeschlagenen Produkte berrechnet.
 * Dieser prozess wird durch einen CRON angestoßen, sodass die Berrechnung NICHT
 * auf Echtzeit-Daten läuft. Hierdurch wird eine hohe Performance erreicht.
 */
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


    }

}