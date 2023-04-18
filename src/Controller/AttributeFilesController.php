<?php

namespace BurkiSchererAG\BSNewsSubmit\Controller;

use Contao\File;
use Contao\Dbafs;
use Contao\Input;
use Contao\Message;
use Contao\NewsModel;
use Contao\Validator;
use Contao\FilesModel;
use Contao\FileUpload;
use Contao\Environment;
use BurkiSchererAG\BSNewsSubmit\DropZone;
use BurkiSchererAG\BSNewsSubmit\FeeHelper;
use Contao\Controller as ContaoController;
use BurkiSchererAG\BSNewsSubmit\FileWidget;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


/**
 * @Route("/file_selection", name=AttributeFilesController::class)
 */
class AttributeFilesController extends AbstractController
{
    public $uploader;
    public $framework;
    public $request;
    public $newsId;

    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    public function __invoke(Request $request)
    {

        $this->request = $request;
        $this->framework->initialize();

        $session = $this->request->getSession();
        $this->newsId = $session->get('newsId', null);

        //Prepare Dropzone
        if ($request->isMethod('GET')) {
            return new Response($this->createDropZone());
        }

        //Handle uploaded files
        if ($request->isMethod('POST') && $request->get('FORM_SUBMIT') == 'tl_upload') {
            return $this->handleUploads();
        }

        //Handle remove files
        if ($request->isMethod('POST') && $request->get('uuid') !== '') {
            //return new Response('UUID:' . $uuid);
            $uuid = $request->get('uuid');
            $field = $request->get('field');

            return $this->deleteFile($uuid, $field);
        }
    }


    /**
     * Create DropZone
     */
    public function createDropZone()
    {

        //Check if the field name is set// Path is correct and allowed
        $strPathUuid =  Input::get('path');

        if (!Validator::isUuid($strPathUuid)) {
            return 'Path Incorrect, may be save it once and reload';
        }

        $objFileModel = FilesModel::findByUuid($strPathUuid);

        $strField = basename($objFileModel->path);

        // return new Response($strField);
        $this->uploader = new DropZone($strPathUuid, $this->request);
        $this->uploader->setName($strField);

        return Message::generate() . '
<form id="uploadForm" dataid="tl_content_' . $strField . '" class="tl_form tl_edit_form" method="post"' . (!empty($this->onsubmit) ? ' onsubmit="' . implode(' ', $this->onsubmit) . '"' : '') . ' enctype="multipart/form-data">
<div class="tl_formbody_edit">
<input type="hidden" name="FORM_SUBMIT" value="tl_upload">
<input type="hidden" name="REQUEST_TOKEN" value="' . REQUEST_TOKEN . '">
<input type="hidden" name="MAX_FILE_SIZE" value="5000000">
<div class="tl_tbox">
<div class="widget">
  <h3>Upload Files</h3>' . $this->uploader->generateMarkup() . '
</div>
</div>
</div>
</form>';
    }



    /**
     * Handle files upload
     */
    public function handleUploads()
    {
        // Ajax request
        if (Environment::get('isAjaxRequest')) {
            $path = Input::post('path');

            //Check permission
            $this->checkPermission();

            $path = FilesModel::findByUuid($path)->path;

            $strField = \basename($path);

            //this file count includes the folder itself
            $fileCount = FilesModel::findMultipleByBasepath($path)->count();

            $maxAllowedFileCount = $GLOBALS['bs_NewsSubmit']['maxAllowedFileCount'];

            //if qouta is full return
            if ($fileCount > $maxAllowedFileCount) {
                $response = new Response("Allowed file count reached");
                $response->setStatusCode(500);
                return $response;
            }

            //Check for allowed file type
            $thisUploadedFile = array($_FILES[$strField]);
            $thisUploadedFileName = $thisUploadedFile[0]['name'];
            $thisUploadedFileExtension = strtolower(substr($thisUploadedFileName, strrpos($thisUploadedFileName, '.') + 1));

            //** FOR THE DEMO, RESTRICT TO SOME FILES **/
            $arrAcceptedExtension = ['jpg', 'jpeg', 'png'];
            //** COMMENT/REMOVE ABOVE LINE later **/

            if (!\in_array($thisUploadedFileExtension, $arrAcceptedExtension)) {
                $response = new Response("Not Allowed file type");
                $response->setStatusCode(500);
                return $response;
            }

            //Everything seems OK upload the file
            $this->objAjax = new FileUpload();
            $this->objAjax->setName($strField);
            $arrUploaded = $this->objAjax->uploadTo($path);

            //You have to sysc files;
            foreach ($arrUploaded as $strFile) {
                Dbafs::addResource($strFile);
            }

            $CE_gallery = $this->makeGalleryElement();
            return new Response($CE_gallery);
        }

        return new Response("Not ajax call ");
    }


    public function checkPermission()
    {
        $pId = Input::post('pid');

        if ($this->newsId == null || $this->newsId != $pId) {
            $response = new Response("Please reload the page");
            $response->setStatusCode(500);
            return $response;
        }
    }

    public function makeGalleryElement()
    {
        $session = $this->request->getSession();
        $path = Input::post('path');
        $CE_GalleryId = $session->get('CE_GalleryId', null);

        $CE_gallery = ContaoController::getContentElement($CE_GalleryId);


        //Make Ce gallery element if not present
        if ($CE_gallery == '' && $CE_GalleryId == null && $path !== null) {
            $objFileModel = FilesModel::findByUuid($path);

            // $CE_galleryModel = new ContentModel();

            // $CE_galleryModel->ptable  = 'tl_news';
            // $CE_galleryModel->pid  = $this->newsId;
            // $CE_galleryModel->tstamp  = time();
            // $CE_galleryModel->sorting  = 1000;
            // $CE_galleryModel->type = 'gallery';
            // $CE_galleryModel->multiSRC = $objFileModel->uuid;
            // $CE_galleryModel->save();

            $CE_galleryModel = FeeHelper::createGalleryCE($this->newsId, $objFileModel);

            $CE_GalleryId = $CE_galleryModel->id;
            $session->set('CE_GalleryId', $CE_GalleryId);
        }


        // //Render and return file widget
        $thisNewsModel = NewsModel::findById($this->newsId);

        $widgetAttributeArr = [
            'id' => 'image_gallery',
            'name' => 'image_gallery',
            'strField' => 'image_gallery',
            'strTable' => 'tl_news',
            'label' => 'image_gallery',
            'type' => 'type',
            'value' => '',
            'newsModelArr' => $thisNewsModel
        ];

        $objFileWidget = new FileWidget($widgetAttributeArr);

        return $objFileWidget->parse();
    }

    /**
     * Handle Deleting of file
     */
    public function deleteFile($uuid, $field)
    {

        //Check uuid validity
        if (!Validator::isUuid($uuid)) {
            return new Response("Wrong uuid Parameter: " . $uuid);
        }

        //Check file exist
        $FileToDelete = FilesModel::findByUuid($uuid)->path;

        if (!file_exists($FileToDelete)) {
            return new Response("File not there: " . $FileToDelete);
        }

        $session = $this->request->getSession();
        $uploadFolderUuid = $session->get('uploadFolder', null);

        $uploadFolderPath = FilesModel::findByUuid($uploadFolderUuid)->path;

        //Only delete file exist inside allowed path
        if (strpos($FileToDelete, $uploadFolderPath) !== false) {
            $objFileToDelete = new File($FileToDelete);
            $objFileToDelete->delete();
            return new Response("Deleted file: " . $FileToDelete);
        }

        return new Response("Not DELETED : " . $FileToDelete);
    }
}
