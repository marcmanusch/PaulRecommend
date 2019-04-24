<?php
/**
 * Created by PhpStorm.
 * User: marc
 * Date: 12.11.18
 * Time: 21:41
 */

use Shopware\Components\CSRFWhitelistAware;
use Enlight\Event\SubscriberInterface;


/**
 * Class Shopware_Controllers_Widgets_LoadArticleWidget
 */
class Shopware_Controllers_Widgets_LoadArticleWidget extends Enlight_Controller_Action implements CSRFWhitelistAware {


    /**
     * @return array
     */
    public function getWhitelistedCSRFActions()
    {
        return [
            'loadArticles'
        ];
    }


    public function loadArticlesAction()
    {

        $view = $this->View();
        $view->loadTemplate("frontend/detail/recommend.tpl");

        $request = $this->Request();
        $sArticle = $request->getParam('sArticle');

        $configReader = $this->container->get('shopware.plugin.config_reader');
        $config = $configReader->getByPluginName('PaulRecommend');

        // get plugin settings
        $active = $config['active'];
        $minDisplay = $config['minDisplay'];
        $useMax = $config['useMax'];

        // Hole die aktuelle ordernumber (Bestellnummer / MPN) aus der aktuellen View.
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

            // Übergebe Wete an View OHNE CACHE!!
            $view->aprioriArticles = $aprioriArticles;
            $view->sArticle = $sArticle;
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