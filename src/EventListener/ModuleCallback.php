<?php

namespace BurkiSchererAG\BSNewsSubmit\EventListener;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\Database;
use Contao\StringUtil;
use Contao\Controller;

class ModuleCallback
{
    /**
     * @Callback(table="tl_module", target="config.onload")
     */
    public function onLoadDataContainer(DataContainer $dc): void
    {
        /**
         * This onload routine is decides
         * weather to show "File Upload Destination" folder section or not depending upon
         * the selected editable fields. If one of these editiable fields is type of filetree 
         * then upload folder setting is shown in dca
         */
        $objModule = Database::getInstance()->prepare("SELECT * FROM tl_module WHERE id=?")->execute($dc->id);
        //$arrModule = $objModule->row();

        if ($objModule->type !== 'bs_NewsSubmit') {
            return;
        }

        Controller::loadDataContainer('tl_news');

        $showFilePath = false;
        $palettes = &$GLOBALS['TL_DCA']['tl_module']['palettes'];

        $bsNewsSubmitEditable = StringUtil::deserialize($objModule->bsNewsSubmitEditable);

        foreach ($bsNewsSubmitEditable as $field) {
            if ($GLOBALS['TL_DCA']['tl_news']['fields'][$field]['inputType'] == 'fileTree') {
                $showFilePath = true;
                break;
            }
        }

        //Add upload destination folder selection to DCA
        if ($showFilePath) {
            $palettes['bs_NewsSubmit'] = str_replace(',disableCaptcha;', ',bsUploadDir, disableCaptcha;', $palettes['bs_NewsSubmit']);
        }
    }
}
