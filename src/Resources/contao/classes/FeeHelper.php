<?php

namespace BurkiSchererAG\BSNewsSubmit;

use Contao\File;
use Contao\Image;
use Contao\Config;
use Contao\System;
use Contao\FilesModel;
use Contao\StringUtil;
use Contao\ContentModel;
use Contao\Image\ResizeConfiguration;

class FeeHelper
{

    public static function createGalleryCE($newsId, $galleryFolderModel)
    {

        $CE_galleryModel = new ContentModel();

        $CE_galleryModel->ptable  = 'tl_news';
        $CE_galleryModel->pid  = $newsId;
        $CE_galleryModel->tstamp  = time();
        $CE_galleryModel->sorting  = 1000 * $GLOBALS['bs_NewsSubmit']['DETAIL_CE_TEXT_FIELD'];
        $CE_galleryModel->type = 'gallery';
        $CE_galleryModel->multiSRC = serialize([$galleryFolderModel->uuid]);

        $CE_galleryModel->save();

        return $CE_galleryModel;
    }


    /*
    * param array();
    * return Files Contao\Model\Collection
    */
    public static function getFilesFromAllowedFolder($folderPath)
    {
        $allowedFolder = $folderPath;
        $arrFilesPool = [];
        $objFilesPools =  FilesModel::findMultipleFilesByFolder($allowedFolder);

        if ($objFilesPools !== null) {
            $arrFilesPool = self::getFiles($objFilesPools);
        }

        return $arrFilesPool;
    }

    /**
     * Get Files list from Obj Model Collection
     */
    public static function getFiles($objFiles)
    {
        $arrValues = [];
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        while ($objFiles->next()) {
            // File system and database seem not in sync
            if (!file_exists($rootDir . '/' . $objFiles->path)) {
                continue;
            }

            if ($objFiles->type == 'folder') {
                $arrValues[$objFiles->uuid] = Image::getHtml('folderC.svg') . ' ' . $objFiles->path;
            } else {
                $objFile = new File($objFiles->path);
                $strInfo = '<span class="filename"> ' . $objFiles->name . ' </span>' .
                    ' <span class="tl_gray">(' . System::getReadableSize($objFile->size) .
                    ($objFile->isImage ? ', ' . $objFile->width . 'x' . $objFile->height . ' px' : '') .
                    ')</span>';

                if ($objFile->isImage) {
                    $strInfo = $objFiles->path;
                    $arrValues[$objFiles->uuid] = self::getPreviewImage($objFile, $strInfo);
                } else {
                    $arrValues[$objFiles->uuid] = Image::getHtml($objFile->icon) . ' ' . $strInfo;
                }
            }
        }
        return $arrValues;
    }


    /**
     * Return the preview image
     *
     * @param File   $objFile
     * @param string $strInfo
     * @param string $strClass
     *
     * @return string
     */
    public static function getPreviewImage(File $objFile, $strInfo, $strClass = 'gimage')
    {
        if ($objFile->viewWidth && $objFile->viewHeight && ($objFile->isSvgImage || ($objFile->height <= Config::get('gdMaxImgHeight') && $objFile->width <= Config::get('gdMaxImgWidth')))) {
            // Inline the image if no preview image will be generated (see #636)
            if ($objFile->height !== null && $objFile->height <= 75 && $objFile->width !== null && $objFile->width <= 100) {
                $image = $objFile->dataUri;
            } else {
                $rootDir = System::getContainer()->getParameter('kernel.project_dir');
                $image = System::getContainer()->get('contao.image.image_factory')->create($rootDir . '/' . $objFile->path, array(100, 75, ResizeConfiguration::MODE_BOX))->getUrl($rootDir);
            }
        } else {
            $image = Image::getPath('placeholder.svg');
        }

        if (strncmp($image, 'data:', 5) === 0) {
            return '<img src="' . $objFile->dataUri . '" width="' . $objFile->width . '" height="' . $objFile->height . '" alt="" class="' . $strClass . '" title="' . StringUtil::specialchars($strInfo) . '">';
        }

        return Image::getHtml($image, '', 'class="' . $strClass . '" title="' . StringUtil::specialchars($strInfo) . '"');
    }
}
