<?php

namespace BurkiSchererAG\BSNewsSubmit\ContaoManager;


use Contao\NewsBundle\ContaoNewsBundle;
use Symfony\Component\HttpKernel\KernelInterface;
use BurkiSchererAG\BSNewsSubmit\BSNewsSubmitBundle;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;

/**
 * Plugin for the Contao Manager.
 *
 * @author Tenzin Tsarma 
 */
class Plugin implements BundlePluginInterface, RoutingPluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create(BSNewsSubmitBundle::class)
                ->setLoadAfter([ContaoNewsBundle::class, 'notification_center'])
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel)
    {

        $file = __DIR__ . '/../Resources/config/routing.yml';

        return $resolver->resolve($file)->load($file);
    }
}
