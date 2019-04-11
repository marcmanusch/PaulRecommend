<?php
/**
 * Created by PhpStorm.
 * User: Marc Manusch
 * Date: 09.11.18
 * Time: 15:31
 */

namespace PaulRecommend;

use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\InstallContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Shopware\Components\Plugin\Context\UninstallContext;

class PaulRecommend extends Plugin
{

    /**
     * @param InstallContext $context
     */
    public function install(InstallContext $context)
    {
        $service = $this->container->get('shopware_attribute.crud_service');
        $service->update('s_articles_attributes', 'recommend_articles', 'string');
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
        $service = $this->container->get('shopware_attribute.crud_service');
        $service->delete('s_articles_attributes', 'recommend_articles');
    }

}