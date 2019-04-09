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

class PaulRecommend extends Plugin
{

    /**
     * @param InstallContext $context
     */
    public function install(InstallContext $context)
    {
    }

    /**
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        $container->setParameter('paul_recommend.plugin_dir', $this->getPath());
        parent::build($container);
    }

}