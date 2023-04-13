<?php

namespace BurkiSchererAG\BSNewsSubmit;

// copied from BE widget Resources/contao/widgets/FileTree.php
use Contao\Folder;
use Contao\System;
use Contao\Widget;
use Contao\FilesModel;
use Contao\StringUtil;
use Contao\ContentModel;
use Contao\FrontendTemplate;
use BurkiSchererAG\BSNewsSubmit\DropZone;
use BurkiSchererAG\BSNewsSubmit\FeeHelper;

class FileWidget extends Widget
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'fe_file_widget';

    protected $folderPath = 'files';

    protected $folderPathStringUuid = '';

    public $galleryFolder;

    protected $dcWidgetAttribute = null;

    protected $strField = 'fieldname';

    /**
     * Load the database object
     *
     * @param array $arrAttributes
     */
    public function __construct($arrAttributes = null)
    {

        parent::__construct($arrAttributes);

        $this->class = 'widget file_selector';

        $this->dcWidgetAttribute = $arrAttributes['dataContainer']->newsModel ?? $arrAttributes['newsModelArr'];

        $this->strField = $arrAttributes['strField'];

        //Since attribute file is used, create a folder to save files anyway.
        $this->getUploadFolder();
    }

    /**
     * getUploadFolder folder to upload
     */
    public function getUploadFolder()
    {
        // dd($this->dcWidgetAttribute);
        if ($this->dcWidgetAttribute->uploadDir) {
            $fileModel = FilesModel::findById($this->dcWidgetAttribute->uploadDir);
            if ($fileModel != null) {
                $baseFolderPath = $fileModel->row()['path'];
                $galleryFolderPath = $baseFolderPath . '/' . $this->strField;

                //This creates folder if not present and return folder obj
                $this->galleryFolder = new Folder($galleryFolderPath);

                $this->folderPathStringUuid = StringUtil::binToUuid($this->galleryFolder->getModel()->uuid);

                $this->folderPath = $galleryFolderPath;
            }
        }

        // Add the scripts
        $GLOBALS['TL_CSS']['dz'] = 'assets/dropzone/css/dropzone.min.css';
        $GLOBALS['TL_JAVASCRIPT']['dz'] = 'assets/dropzone/js/dropzone.min.js';
        $GLOBALS['TL_JAVASCRIPT']['sortable'] = TL_PATH . '/bundles/bsnewssubmit/js/Sortable.min.js';
        $GLOBALS['TL_JAVASCRIPT']['mmfeefile'] = TL_PATH . '/bundles/bsnewssubmit/js/mmfeefile.js';
        $GLOBALS['TL_CSS']['mmfeefile'] = TL_PATH . '/bundles/bsnewssubmit/css/mmfeefile.css';
    }


    /**
     * Generate the widget and return it as string
     *
     * @return string
     */
    public function generate()
    {

        $arrAllFilesInPool = FeeHelper::getFilesFromAllowedFolder($this->folderPath);
        $request = System::getContainer()->get("request_stack");
        $session = $request->getSession();

        $CE_GalleryId = $session->get('CE_GalleryId', null);
        $CE_GalleryModel = ContentModel::findById($CE_GalleryId);

        $orderSrcArr = StringUtil::deserialize($CE_GalleryModel->orderSRC);

        //Sort arrFiles if there are more than one
        if (is_array($orderSrcArr) && count($orderSrcArr) > 1) {
            $arrFilesPool = array_replace(array_flip($orderSrcArr), $arrAllFilesInPool);
        } else {
            $arrFilesPool = $arrAllFilesInPool;
        }

        $sortOrder = '';

        $maxAllowedFileCount = $GLOBALS['bs_NewsSubmit']['maxAllowedFileCount'];

        $currentCount = count($arrFilesPool);

        $uploadAllowCount = $maxAllowedFileCount - count($arrFilesPool);

        $objTemplate = new FrontendTemplate('bs_file_widget');
        $objTemplate->maxAllowedFileCount = $maxAllowedFileCount;
        $objTemplate->currentCount = $currentCount;
        $objTemplate->uploadAllowCount = $uploadAllowCount;
        $objTemplate->sortOrder = $sortOrder;
        $objTemplate->folderPathStringUuid = $this->folderPathStringUuid;
        $objTemplate->strField = $this->strField;
        $objTemplate->maxAllowedFileCount = $maxAllowedFileCount;
        $objTemplate->arrFilesPool = $arrFilesPool;

        //If there are gallery files in folder but no gallery element, then mark it 
        if (count($arrAllFilesInPool) && $CE_GalleryId == null) {
            $objTemplate->filesNoGallery = true;

            //create gallery again
            $galleryFolderModel = $this->galleryFolder->getModel();
            $CE_galleryModel = FeeHelper::createGalleryCE($this->dcWidgetAttribute->id, $galleryFolderModel);
            $CE_GalleryId = $CE_galleryModel->id;
            $session->set('CE_GalleryId', $CE_GalleryId);
        }

        return $objTemplate->parse();
    }


    /**
     * Get all selected files Model collection list
     */
    public function getSelectedFiles($varValueArr)
    {
        $arrValues = [];
        $objFiles = FilesModel::findMultipleByUuids((array) $varValueArr);
        //$allowedDownload = StringUtil::trimsplit(',', strtolower(Config::get('allowedDownload')));

        if ($objFiles !== null) {
            $arrValues = FeeHelper::getFiles($objFiles);
        }

        return $arrValues;
    }
}
