<?php

namespace BurkiSchererAG\BSNewsSubmit\EventListener\DataContainer;

use Contao\ModuleModel;
use Contao\DataContainer;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @Callback(table="tl_module", target="config.onload")
 */
class BSModuleCallback
{
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function __invoke(DataContainer $dc = null): void
    {

        if (null === $dc || !$dc->id || 'edit' !== $this->requestStack->getCurrentRequest()->query->get('act')) {
            return;
        }

        $module = ModuleModel::findById($dc->id);

        if (null === $module && $module->type !== 'bs_NewsSubmit') {
            return;
        } else {
            $GLOBALS['TL_DCA']['tl_module']['fields']['bs_checkbox']['label'] = &$GLOBALS['TL_LANG']['tl_module']['news_submit_bs_checkbox'];
        }
    }
}
