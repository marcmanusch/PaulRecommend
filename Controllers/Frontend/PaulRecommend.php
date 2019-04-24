<?php
/**
 * Created by PhpStorm.
 * User: marc
 * Date: 12.11.18
 * Time: 21:41
 */

/**
 * https://forum.shopware.com/discussion/48888/frontend-controller-innerhalb-plugin-abrufen
 */

use Shopware\Components\CSRFWhitelistAware;
use Shopware\Bundle\AttributeBundle\Service\DataLoader as AttributeDataLoader;
use Shopware\Bundle\AttributeBundle\Service\DataPersister as AttributeDataPersister;

/**
 * Class Shopware_Controllers_Frontend_PaulRecommend
 */
class Shopware_Controllers_Frontend_PaulRecommend extends Enlight_Controller_Action implements CSRFWhitelistAware {

    public function addArticlesAction()
    {
        # no template
        Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();

        $recomendedArticles = $this->Request()->getParam('recomendedArticles');

        //Füge jeden Artikel dem Warenkorb hinzu
        foreach ($recomendedArticles as $article) {

            try {
                // Hole den Warenkorb
                $basketId = Shopware()->Modules()->Basket()->sAddArticle($article, 1);

                /* @var $attributeDataLoader AttributeDataLoader */
                $attributeDataLoader = $this->get( "shopware_attribute.data_loader" );

                /* @var $attributeDataPersister AttributeDataPersister */
                $attributeDataPersister = $this->get( "shopware_attribute.data_persister" );

                // Speichere die Artikel in den Warenkorb
                $attributes = $attributeDataLoader->load( "s_order_basket_attributes", $basketId );
                $attributeDataPersister->persist( $attributes, "s_order_basket_attributes", $basketId );

            } catch (Exception $e) {}
        }

        // Zum Warenkorb weiterleiten, nachdem alle Artikel im Warenkorb sind
        $this->redirect(array(
            'module'     => "frontend",
            'controller' => "checkout",
            'action'     => "cart"
        ));

    }

    public function getWhitelistedCSRFActions()
    {
        return [
            'addArticles'
        ];
    }
}