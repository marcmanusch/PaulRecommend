<?php
/**
 * Created by PhpStorm.
 * User: marc
 * Date: 16.04.19
 * Time: 12:10
 */

namespace PaulRecommend\Subscriber;

use Enlight\Event\SubscriberInterface;
use PaulRecommend\Vendor\Apriori;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LoadRecommendProducts implements SubscriberInterface
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
        $minDisplay = $config['minDisplay'];
        $useMax = $config['useMax'];

        // Hole die aktuelle ordernumber (Bestellnummer / MPN) aus der aktuellen View.
        $sArticle = $view->getAssign('sArticle');
        $mainDetailId = $sArticle['articleDetailsID'];
        $ordernumber = $sArticle['ordernumber'];

        if($active) {
            $builder = $this->loaData($mainDetailId);
            $stmt = $builder->execute();
            $recomendArticles = $stmt->fetchAll();
            $recomendArticles = json_decode($recomendArticles[0]['recommend_articles'], true);

            /**
             * wähle zufällig Artikel aus
             * Die Länge des Arrays ist variabel
             */
            //letze abziehen da immer leer
            $laengeArray = count($recomendArticles) - 1;
            if($laengeArray <= $minDisplay) {
                $laengeArray = $minDisplay;
            }

            //Auswahl des Arrays mit Zufallszahl (zwischen 2 und länge des Arrays)
            $arrayNummer = random_int($minDisplay, $laengeArray);

            if($useMax) {
                $arrayNummer = count($recomendArticles) - 1;
            }

            foreach($recomendArticles[$arrayNummer] as $recomendArticle) {

                foreach($recomendArticle as $key => $ordernumberApri) {
                    try {
                        $articleModule = Shopware()->Modules()->Articles();
                        $articleID = $articleModule->sGetArticleIdByOrderNumber($ordernumberApri);
                        $article = $articleModule->sGetArticleById($articleID);
                        $aprioriArticles[$key] = $article;
                    }catch (\Exception$e) {}
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
            $view->assign('aprioriArticles', $aprioriArticles);

        }
    }

    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     * @param $mainDetailId
     */
    private function loaData($mainDetailId)
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->container->get('dbal_connection');
        $builder = $connection->createQueryBuilder();
        $builder->select('recommend_articles')
            ->from('s_articles_attributes', 'saa')
            ->where('saa.articledetailsID = \'' . $mainDetailId . '\'');
        return $builder;
    }

}
