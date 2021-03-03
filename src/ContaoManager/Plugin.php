<?php

namespace BurkiSchererAG\BSNewsSubmit\ContaoManager;


use BurkiSchererAG\BSNewsSubmit\BSNewsSubmitBundle;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\NewsBundle\ContaoNewsBundle;

/**
 * Plugin for the Contao Manager.
 *
 * @author Tenzin Tsarma 
 */
class Plugin implements BundlePluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create(BSNewsSubmitBundle::class)
                ->setLoadAfter([ContaoNewsBundle::class])
        ];
    }
}
