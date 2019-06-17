<?php
/**
 * Created by PhpStorm.
 * User: Marc Manusch
 * Date: 09.11.18
 * Time: 15:31
 */

namespace PaulRecommend;

use Doctrine\ORM\Tools\SchemaTool;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\InstallContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Shopware\Components\Plugin\Context\UninstallContext;
use PaulRecommend\Models\PlentyMarketsOrders;


class PaulRecommend extends Plugin
{

    /**
     * @param InstallContext $context
     */
    public function install(InstallContext $context)
    {
        $service = $this->container->get('shopware_attribute.crud_service');
        $service->update('s_articles_attributes', 'recommend_articles', 'string');
        $service->update('s_articles_attributes', 'show_recommend_articles', 'boolean', [
            'label' => 'Blende passende Artikel aus',

            //user has the opportunity to translate the attribute field for each shop
            'translatable' => true,

            //attribute will be displayed in the backend module
            'displayInBackend' => true,

            //numeric position for the backend view, sorted ascending
            'position' => 0,

            //user can modify the attribute in the free text field module
            'custom' => true,

        ]);

        $this->updateSchema();
    }

    /**
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        $container->setParameter('paul_recommend.plugin_dir', $this->getPath());
        parent::build($container);
    }

    public function uninstall(UninstallContext $context)
    {
        try {
            $service = $this->container->get('shopware_attribute.crud_service');
            $service->delete('s_articles_attributes', 'recommend_articles');

            /** @var ModelManager $entityManager */
            $entityManager = $this->container->get('models');

            $tool = new SchemaTool($entityManager);

            $classMetaData = [
                $entityManager->getClassMetadata(PlentyMarketsOrders::class)
            ];

            $tool->dropSchema($classMetaData);

        }catch (\Exception $e) {}

    }

    private function updateSchema()
    {
        /** @var ModelManager $entityManager */
        $entityManager = $this->container->get('models');

        $tool = new SchemaTool($entityManager);

        $classMetaData = [
            $entityManager->getClassMetadata(PlentyMarketsOrders::class)
        ];

        $tool->createSchema($classMetaData);
    }


}