<?php

namespace BurkiSchererAG\BSNewsSubmit\Controller\FrontendModule;

use Contao\Date;
use Contao\File;
use Contao\Dbafs;
use Contao\Input;
use Contao\Folder;
use Contao\System;
use Contao\Message;
use Contao\Template;
use Contao\NewsModel;
use Contao\Controller;
use Contao\FilesModel;
use Contao\StringUtil;
use Haste\Util\Format;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\ContentModel;
use Contao\FrontendTemplate;
use Doctrine\DBAL\Connection;
use BurkiSchererAG\BSNewsSubmit\FeeHelper;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\Response;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;

class NewsSubmitController extends AbstractFrontendModuleController
{

    private $connection;

    private $security;

    private $scopeMatcher;

    protected $strTable = 'tl_news';

    private $request;

    private $allowedCeText = [];

    /**
     * new News Model Object
     */
    public $objNews;


    /**
     * Already existing News Model
     */
    public $newsModel = null;

    /**
     * CE of already existing News Model
     */
    private $arrNewsContentElement = [];
    private $arrTempContentElement = [];

    public function __construct(
        Connection $Connection,
        Security $security,
        ScopeMatcher $scopeMatcher
    ) {
        $this->connection = $Connection;
        $this->security = $security;
        $this->scopeMatcher = $scopeMatcher;

        //Add tinyMCE to the html header
        $tinyMCETemplate = new FrontendTemplate('fe_tinyMCE');
        $tinyMCETemplate->parse();
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        global $objPage;

        $this->request = $request;

        /*
            If there is file upload in editable fields,
            make sure upload destination is set
        */
        $editableFields = StringUtil::deserialize($model->bsNewsSubmitEditable);

        if (
            $this->scopeMatcher->isBackendRequest($request) &&
            sizeof(array_intersect(['singleSRC', 'enclosure'], $editableFields)) > 0 &&
            !$model->bsUploadDir
        ) {
            $template->link .= ' <span style="color: red">(Please set upload folder)</span>';
        }
        /* end upload folder check */

        // Set all properties which are need for other functions
        $this->model = $model;
        $this->editable = $editableFields;
        $this->user = $this->security->getUser();

        if ($this->editable == null) {
            return new Response('No editable fields defined for the Module. Please check you module settings');
        }

        System::loadLanguageFile($this->strTable, $objPage->language);
        Controller::loadDataContainer($this->strTable);

        //check and get news model if there is already one existing
        $this->getNewsModel();

        if ($this->newsModel !== null) {
            //Load alread existing news
            $this->objNews = $this->newsModel;
        } else {
            //or create a new news
            $this->objNews = new NewsModel();
        }

        $template->fields = '';
        $template->tableless = $model->tableless;

        $objCaptcha = null;
        $doNotSubmit = false;
        $hasUpload   = false;
        $row         = 0;
        $max_row = count($this->editable);

        $strFormId = $this->strTable . '_' . $model->id;

        //This is set early, because its needed in function to create upload folder
        $template->formId  =  $strFormId;

        $this->Template = $template;

        // Captcha, Check Captcha early, as creating upload folder depend on errors
        // but add to Widget/FFL at the end
        if (!$model->disableCaptcha) {

            $arrCaptcha = [
                'id' => 'newssubmit',
                'label' => $GLOBALS['TL_LANG']['MSC']['securityQuestion'],
                'type' => 'captcha',
                'mandatory' => true,
                'required' => true
            ];

            /** @var FormCaptcha $strClass */
            $strClass = $GLOBALS['TL_FFL']['captcha'] ?? null;

            // Fallback to default if the class is not defined
            if (!class_exists($strClass)) {
                $strClass = 'FormCaptcha';
            }

            /** @var FormCaptcha $objCaptcha */
            $objCaptcha = new $strClass($arrCaptcha);

            if (Input::post('FORM_SUBMIT') == $strFormId) {
                $objCaptcha->validate();

                if ($objCaptcha->hasErrors()) {
                    $doNotSubmit = true;
                }
            }

            // //This is required here.
            $template->hasError = $doNotSubmit;
        }


        // Build the form
        foreach ($this->editable as $field) {

            $arrData = &$GLOBALS['TL_DCA'][$this->strTable]['fields'][$field];

            // Continue if inputType does not exist
            if (!isset($arrData['inputType'])) {
                --$max_row;
                continue;
            }

            // Map checkboxWizards to regular checkbox widgets
            if ($arrData['inputType'] == 'checkboxWizard') {
                $arrData['inputType'] = 'checkbox';
            }

            // Set Widget Classes
            if ($arrData['inputType']) {
                /** @var \Widget $strClass */
                $strClass = $GLOBALS['TL_FFL'][$arrData['inputType']] ?? null;
            }

            // Set upload folder
            if (
                ($arrData['inputType'] == 'fileTree' || $arrData['inputType'] == 'multiSRC')
                && !$doNotSubmit
                && Input::post('FORM_SUBMIT') == $strFormId
            ) {
                $arrData['eval']['uploadFolder'] = $this->getUploadFolderUuid($field);
            }

            // Map fileTrees to upload widgets (see #8091)
            if ($arrData['inputType'] == 'fileTree') {

                // Get allowed extentions as string from contao default array values for images
                // for enclosure it is already string value
                if ('%contao.image.valid_extensions%' == $arrData['eval']['extensions']) {
                    $varValue = System::getContainer()->getParameter('contao.image.valid_extensions');
                    $extention = strtolower(implode(',', \is_array($varValue) ? $varValue : StringUtil::trimsplit(',', $varValue)));
                    $arrData['eval']['extensions'] = $extention;
                }

                //Images are stored as binary value. mark it as so, to convert it in to uuid in template
                if (strpos($arrData['sql'], 'binary') !== false) {
                    $arrData['eval']['isBinVal'] = true;
                }

                $strClass = 'BurkiSchererAG\BSNewsSubmit\FormFileUploadFEE';
            }

            //Set TextArea Widget Classes
            if ($arrData['inputType'] == 'textarea') {
                if (strpos($field, 'detailCE') !== false) {
                    $strClass = 'BurkiSchererAG\BSNewsSubmit\FormTextAreaFEE';
                }
            }

            //For Gallery Images
            //Do not show this until a news is already created 
            //We needed the folder to upload files.
            if ($this->newsModel !== null && $arrData['inputType'] == 'multiSRC') {

                $strClass = 'BurkiSchererAG\BSNewsSubmit\FileWidget';

                if (!$doNotSubmit && Input::post($field) !== null) {
                    $this->updateGallerySorting($field);
                }
            }

            // Continue if the class does not exist
            if (!class_exists($strClass)) {
                // dump($arrData['eval']['feEditable'] . ' - ' . $strClass); //Log
                --$max_row;
                continue;
            }

            $arrData['eval']['required']    = $arrData['eval']['mandatory'] ?? null;
            $arrData['eval']['tableless']   = $model->tableless;
            $arrData['eval']['placeholder'] = $arrData['label'] ? $arrData['label'][0] : '';

            //Set default $varValue;
            $varValue = $this->objNews->$field ?? Input::post($field);

            //Make Frontend Form fields from dca widget
            $objWidget = new $strClass($strClass::getAttributesFromDca($arrData, $field, $varValue, $field, $this->strTable, $this));

            // Append the module ID to prevent duplicate IDs (see #1493)
            $objWidget->id = $field . '_' . $model->id;
            $objWidget->storeValues = true;
            $objWidget->name = $field;


            //Default value
            $objWidget->value = $varValue;

            if ($this->arrNewsContentElement && in_array($field, $this->allowedCeText) && $this->arrNewsContentElement[$field]) {
                $objWidget->value = $this->arrNewsContentElement[$field]->row()['text'] ?? '';
            }

            $objWidget->rowClass = 'row_' . $row . (($row == 0) ? ' row_first' : (($row == ($max_row - 1)) ? ' row_last' : '')) . ((($row % 2) == 0) ? ' even' : ' odd');
            $objWidget->class .= ' ' . $objWidget->rowClass;

            //Make tinyMCE editor, where needed
            if (($arrData['eval']['rte'] ?? null)  == 'tinyMCE') {
                $objWidget->class .= ' tinyMCE';
            }

            //Add label, where it is missing, mostly FormTextAreaFEE
            if (!$objWidget->label) {
                $objWidget->label = $GLOBALS['TL_LANG']['tl_news'][$objWidget->name][0] ?? $objWidget->name;
            }

            if ($objWidget instanceof \uploadable) {
                $hasUpload = true;
            }

            // Validate the form data, if doNotSubmit flag is not set
            if (!$doNotSubmit && Input::post('FORM_SUBMIT') == $strFormId) {

                $objWidget->validate();

                //teaser html is encoded
                if (($arrData['eval']['rte'] ?? null)  == 'tinyMCE') {
                    $varValue = StringUtil::decodeEntities($objWidget->value);
                } else {
                    $varValue = $objWidget->value;
                }

                $rgxp = $arrData['eval']['rgxp'] ?? '';

                // Convert date formats into timestamps (check the eval setting first -> #3063)
                // But check if we are editing already existing news, in such case don't update date/time
                if ($this->newsModel == null && $varValue !== null && $varValue !== '' && \in_array($rgxp, ['date', 'time', 'datim'])) {
                    try {
                        $objDate = new Date($varValue, Date::getFormatFromRgxp($rgxp));
                        $varValue = $objDate->tstamp;
                    } catch (\OutOfBoundsException $e) {
                        $objWidget->addError(sprintf($GLOBALS['TL_LANG']['ERR']['invalidDate'], $varValue));
                    }
                }

                if ($varValue !== null && $varValue !== '' && $rgxp == 'url') {
                    //Copy from Contao Widget/HttpUrlListener.php + Check domain name
                    if (!preg_match('~^https?://~i', $varValue) && !filter_var($varValue, FILTER_VALIDATE_URL)) {
                        $objWidget->class = 'error';
                        $objWidget->addError($GLOBALS['TL_LANG']['ERR']['invalidHttpUrl']);
                    }
                }

                // Trigger the save_callback (see #5247) ???? Not sure doing it right?? To check with real callback
                if (\is_array($arrData['save_callback'] ?? null) && $objWidget->submitInput() && !$objWidget->hasErrors()) {
                    foreach ($arrData['save_callback'] as $callback) {
                        try {
                            if (\is_array($callback)) {
                                System::importStatic($callback[0]);
                                $varValue = $this->{$callback[0]}->{$callback[1]}($varValue, null);
                            } elseif (\is_callable($callback)) {
                                $varValue = $callback($varValue, null);
                            }
                        } catch (ResponseException $e) {
                            throw $e;
                        } catch (\Exception $e) {
                            $objWidget->class = 'error';
                            $objWidget->addError($e->getMessage());
                        }
                    }
                }

                // Store the current value
                // Do not submit the field if there are errors
                if ($objWidget->hasErrors()) {
                    $doNotSubmit = true;
                } elseif ($objWidget->submitInput()) {

                    // Set the correct empty value (see #6284, #6373)
                    if ($varValue === '') {
                        $varValue = $objWidget->getEmptyValue();
                    }

                    // Set the new value
                    $this->objNews->$field = $varValue;

                    //custom (non db fields) values inside $this->objNews get reset when there is a fileUpload field,
                    //you can't store it $this->objNews, so new arrTempContentElement variable is introduced
                    $this->arrTempContentElement[$field] = $varValue;
                }
            }

            //Add teaser preview Image
            if ($arrData['inputType'] == 'fileTree' && $objWidget->value) {
                $fieldFile = FilesModel::findByUuid($objWidget->value);

                if ($fieldFile != null) {
                    $fieldFile = FilesModel::findByUuid($objWidget->value)->path;
                    $objFile = new File($fieldFile);
                    if ($objFile->isImage) {
                        $objWidget->fieldImage = FeeHelper::getPreviewImage($objFile, $fieldFile);
                    }
                    $objWidget->fieldPath = $fieldFile;
                }
            }

            $temp = $objWidget->parse();
            $template->fields .= $temp;

            ++$row;
        }

        // dd($this->objNews->row());
        // Add Captcha at the end
        if (!$model->disableCaptcha) {
            //Parse captcha and add to fields
            $strCaptcha = $objCaptcha->parse();
            $template->fields .= $strCaptcha;
        }

        //Add news creator member for logged in
        if ($this->user) {
            $this->objNews->member = $this->user->id;
        }

        $template->hasError = $doNotSubmit;
        $template->messages = Message::generate();
        $template->slabel  = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['saveData']);
        $template->enctype = $hasUpload ? 'multipart/form-data' : 'application/x-www-form-urlencoded';
        $template->action  = $request->getPathInfo() . '?news_id=' . Input::get('news_id');

        // Redirect or reload if there was no error
        if (Input::post('FORM_SUBMIT') == $strFormId && !$doNotSubmit) {

            //Create News
            $newId = $this->createNewsOrUpdate();

            $session = $this->request->getSession();
            $session->set('newsId', $newId);

            // Check whether there is a jumpTo page // Never redirect
            if (0 && ($objJumpTo = $model->getRelated('jumpTo')) !== null) {
                // $this->jumpToOrReload($objJumpTo->row());
                Controller::redirect($objJumpTo->getFrontendUrl());
            }

            Message::addConfirmation($GLOBALS['TL_LANG']['MSC']['savedData']);

            Controller::redirect($request->getPathInfo() . '?news_id=' . $newId);
        }

        return $template->getResponse();
    }


    /**
     * Create a new News
     */
    public function createNewsOrUpdate()
    {

        $arrAttachtment = []; //Add file path to notifcation later on
        $contentElement = [];
        $slug_seed = $this->objNews->headline ?: 'newsurl';

        //Add this only for new news
        if ($this->newsModel == null) {
            $this->objNews->tstamp  = time();
            $this->objNews->date = time();
            $this->objNews->time = time();
        }

        //news Archive
        $this->objNews->pid  = $this->model->bsNewsSubmitArchive;
        $newsArchive = $this->objNews->getRelated('pid')->row();
        $this->objNews->author = $newsArchive['newsOwner'];

        //Generate alias: or should we leave it when updating news
        $slugOptions = $newsArchive['jumpTo'];
        $aliasExists = function (string $alias): bool {
            // https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/data-retrieval-and-manipulation.html
            return $this->connection->executeStatement("SELECT id FROM $this->strTable WHERE alias=?", array($alias)) > 0;
        };
        $this->objNews->alias = System::getContainer()->get('contao.slug')->generate($slug_seed, $slugOptions, $aliasExists);


        //If there is an url value then set link target
        if ($this->objNews->url) {
            //Add source type
            $this->objNews->source = 'external';
            //Add target_blank
            $this->objNews->target = 1;
        }


        //If there were uploads then add the field to $this->objNews
        //Also set the Template->enctype, before calling fn createNews
        if ($this->Template->getData()['enctype'] == 'multipart/form-data' && isset($_SESSION['FILES'])) {

            $projectDir = System::getContainer()->getParameter('kernel.project_dir'); //TL_ROOT
            $host = $this->request->getSchemeAndHttpHost();

            foreach (\array_keys($_SESSION['FILES']) as $fieldName) {
                //enclosure; check also session file key is in the editable list
                if (\in_array($fieldName, $this->editable) && $fieldName == 'enclosure') {
                    $this->objNews->addEnclosure = 1;
                    $this->objNews->{$fieldName} = $_SESSION['FILES'][$fieldName]['uuid'];
                    $arrAttachtment[$fieldName] = str_replace([$projectDir, ' '], [$host, '%20'], $_SESSION['FILES'][$fieldName]['tmp_name']);
                }

                //Teaser singleSRC
                if (\in_array($fieldName, $this->editable) && $fieldName == 'singleSRC') {
                    $this->objNews->addImage = 1;
                    $imgFileModel = FilesModel::findByUuid($_SESSION['FILES'][$fieldName]['uuid']);
                    $this->objNews->{$fieldName} = $imgFileModel->uuid;
                    $arrAttachtment['teaser_image'] = str_replace([$projectDir, ' '], [$host, '%20'], $_SESSION['FILES'][$fieldName]['tmp_name']);
                }
            }
        }

        // dd($this->objNews->row());
        //Store if there is any detail text, by creating content element
        $contentElement = array_filter($this->arrTempContentElement, function ($key) {
            return strpos($key, 'detailCE') === 0;
        }, ARRAY_FILTER_USE_KEY);

        $objNewNews = $this->objNews->save();

        if ($objNewNews !== null) {
            //Create content elements if there are any
            if (count($contentElement)) {
                $index = 0;

                foreach ($contentElement as $nameKey => $element) {
                    //Create CE if it is new news or not in arrNewsContentElement
                    if (!isset($this->arrNewsContentElement[$nameKey])) {

                        //Donot create empty text element
                        if (strlen(trim($element)) < 1) {
                            continue;
                        }

                        $ce_text['pid'] = $objNewNews->id;
                        $ce_text['ptable'] = $this->strTable;
                        $ce_text['type'] = 'text';
                        $ce_text['sorting'] = '10' . $index++ * 10;
                        $ce_text['tstamp'] = time();
                        $ce_text['text'] = $element;
                        // $this->connection->prepare("INSERT INTO tl_content %s")->set($ce_text)->execute();
                        $this->connection->insert('tl_content', $ce_text);
                    } else {
                        //Update existing CE.
                        $this->arrNewsContentElement[$nameKey]->text = $element;
                        $this->arrNewsContentElement[$nameKey]->save();
                    }
                }
            }

            //Add pseudo property to objNewNews, to store file path information for Notification
            //which you can access as ##{fieldName}_path## in notification
            if ($this->objNews->singleSRC) {
                $objNewNews->{'teaser_image_path'} = FilesModel::findByUuid($this->objNews->singleSRC)->path;
            }

            if ($this->objNews->enclosure) {
                $objNewNews->{'enclosure_path'} = FilesModel::findByUuid($this->objNews->enclosure)->path;
            }

            $this->sendNotification($objNewNews);
        }

        return $objNewNews->id;
    }

    /**
     * Find a news Model if there exist one
     * @return NewsModel
     */
    public function getNewsModel()
    {

        $session = $this->request->getSession();

        //Session id of the last saved news id
        $sessionNewsId = $session->get('newsId');

        //Supposedly id of the news
        $id = Input::get('news_id');

        //'For the future';
        $email = null;
        $newsModel = null;

        //If there is no limit to add and there is no news id given
        if (!$this->model->bs_checkbox && $id == null && $this->user == null) {
            return;
        }

        //Try to load the news with id, only when multiple news is allowed and for logged in users
        if (
            !$this->model->bs_checkbox && //not set = Allow multiple
            $id !== null
            && ($this->user !== null || $sessionNewsId == $id || $email !== null) //This is to prevent, anybody editing news with id.
        ) {
            $newsModel = NewsModel::findById($id);
        } else if (
            $this->model->bs_checkbox && //Allow only one
            ($this->user !== null || $email !== null)
        ) {
            //No news with the $id found, so try to find by user->id // findOneBy gets first/or with lowest id value
            $newsModel = NewsModel::findOneBy(array('tl_news.member=? or tl_news.email=?'), array($this->user->id, $email), []);
        }

        //Still no News found
        if (
            $newsModel == null
            // || $newsModel->member !== '0' && $newsModel->member !== $this->user->id && $email == null
            // || $newsModel->email !== '' && $newsModel->email !== $email && $this->user == null
        ) {
            return;
        }

        $this->newsModel = $newsModel;

        //Store relevant info in Session, which can be use for varification in ajax call
        $session->set('newsId', $this->newsModel->id);
        $session->set('uploadFolder', $this->newsModel->uploadDir);
        if ($this->user !== null) {
            $session->set('userId', $this->user->id);
            $session->set('email', $email ?? $this->user->email);
        }

        //Get the new's content elements
        $contentModel = ContentModel::findBy(array("tl_content.pid=? AND tl_content.invisible=''", "tl_content.ptable=?"), array($newsModel->id, 'tl_news'), ['order' => 'sorting']);

        //Reset session Gallery id to null
        $session->set('CE_GalleryId', null);

        if ($contentModel !== null) {

            //Check if there are editable text element: 
            //It can be so the news has CE text but later on text editable it unchecked from module.
            //Also there may be more text elements in BE than editable 
            $this->allowedCeText = array_filter($this->editable, function ($key) {
                return strpos($key, 'detailCE_') === 0;
            });

            //reset array index    
            $this->allowedCeText = array_values($this->allowedCeText);

            $i = 0;

            while ($contentModel->next()) {

                $currrentContent = $contentModel->current();

                //Check if there is text elment and its editable
                if (count($this->allowedCeText) && $currrentContent->type == 'text' && $i < count($this->allowedCeText)) {
                    $this->arrNewsContentElement[$this->allowedCeText[$i]] = $contentModel->current();
                    $i++;
                } else if ($currrentContent->type == 'gallery') {
                    $session->set('CE_GalleryId', $currrentContent->id);
                }
            }
        }
    }


    /**
     * Set UploadFolder and return its Uuid
     * @return uuid
     */
    public function getUploadFolderUuid($field)
    {

        //This is main upload folder defined from module NewsSubmit
        $uuid = $this->model->bsUploadDir;

        //Set base folder for news inside above UploadDir
        if ($uuid == null) {
            $basePath = 'files';
        } else {
            $basePath = FilesModel::findById($uuid)->row()['path'];
        }

        $uploadDir = $basePath;
        $uploadDirModel = FilesModel::findById($this->newsModel->uploadDir);


        if ($uploadDirModel !== null) {
            $uploadDir = $uploadDirModel->row()['path'];
        }

        // If the news already exist, then try to get the folder from image path //Not used
        else if (0 && $this->newsModel !== null && $this->newsModel->singleSRC !== null) {
            $filePath = FilesModel::findById($this->newsModel->singleSRC)->row()['path'];

            $uploadDir = dirname($filePath);
            $objFolder = new Folder($uploadDir);
            $uploadDirUuid = $objFolder->getModel()->uuid;

            //Set news uploadDir if not set already
            if ($this->newsModel->uploadDir == null) {
                $this->newsModel->uploadDir = $uploadDirUuid;
                $this->newsModel->save();
            }
        }
        // Create new folder only when the form is sumbitted without error
        else if (
            Input::post('FORM_SUBMIT') == $this->Template->formId && !$this->Template->hasError
        ) {

            //Add this only for new news, when $this->objNews->id is empty
            $this->objNews->tstamp = time();

            //save once to get id
            $this->objNews->save();


            $newFolder = date('Ymd-Hi') . '-' . $this->objNews->id;
            $uploadDir = $basePath . '/' . $newFolder;


            $objFolder = new Folder($uploadDir);
            $uploadDirUuid = $objFolder->getModel()->uuid;

            $this->objNews->uploadDir  = $uploadDirUuid;
            $this->objNews->save();
        }

        if ($field == 'singleSRC') {
            $uploadDir .= '/teaser';
        } else if ($field == 'enclosure') {
            $uploadDir .= '/enclosure';
        } else if ($field == 'image_gallery') {
            $uploadDir .= '/image_gallery';
        }

        $objFolder = FilesModel::findByPath($uploadDir);

        if (!$objFolder || !is_dir($uploadDir)) {
            $objFolder = new Folder($uploadDir);
            $uuid = $objFolder->getModel()->uuid;
        } else {
            $uuid = $objFolder->uuid;
        }

        Dbafs::addResource($uploadDir);

        return $uuid;
    }


    /**
     * This set the sorting order of Gallery
     */
    public function updateGallerySorting($field)
    {
        $session = $this->request->getSession();
        $CE_GalleryId = $session->get('CE_GalleryId', null);

        $CE_GalleryModel = ContentModel::findById($CE_GalleryId);

        $sortingArr = explode(',', Input::post($field));

        //don't do anything if there is no gallery or with only 1 image
        if ($CE_GalleryModel == null || count($sortingArr) <= 1) {
            return;
        }

        $orderSrcArr = [];

        foreach ($sortingArr as $v) {
            if ($v !== '') {
                $orderSrcArr[] = StringUtil::uuidToBin($v);
            }
        }

        $orderSRC = serialize($orderSrcArr);
        $CE_GalleryModel->orderSRC = $orderSRC;
        $CE_GalleryModel->save();
    }


    /**
     * Send Notification Email
     */
    public function sendNotification($objNewNews)
    {
        global $objPage;
        $arrMember = [];
        $arrTokens = [];
        $objMember = null;

        if ($this->user) {
            $arrMember = $this->user->getData();
        } else {
            //Check if there is a member by the email
            if ($objNewNews->email) {
                $objMember = MemberModel::findByEmail($objNewNews->email);

                if ($objMember !== null) {
                    $arrMember = $objMember->row();
                }
            }

            //Also add guest information
            $arrTokens['GuestCompany'] = $objNewNews->company;
            $arrTokens['GuestTitle'] = $objNewNews->designation;
            $arrTokens['GuestFirstname'] = $objNewNews->firstname;
            $arrTokens['GuestLastname'] = $objNewNews->lastname;
            $arrTokens['GuestEmail'] = $objNewNews->email;
        }

        // Add member fields to token
        if ($objMember !== null) {
            foreach ($arrMember as $k => $v) {
                if (!\is_object($v)) {
                    $arrTokens['member_' . $k] = Format::dcaValue('tl_member', $k, $v);
                }
            }
        }

        // Add News fields to token
        foreach ($objNewNews->row() as $k => $v) {
            if (!\is_object($v)) {
                $arrTokens['news_' . $k] = Format::dcaValue($this->strTable, $k, $v);
            }
            if ($k == 'teaser_image_path') {
                $arrTokens['news_teaser_image_path'] = $v;
            }
            if ($k == 'enclosure_path') {
                $arrTokens['news_enclosure_path'] = $v;
            }
        }

        //Add Module data
        foreach ($this->model->row() as $k => $v) {
            if (!\is_object($v)) {
                $arrTokens['newssubmit_mod_' . $k] = Format::dcaValue('tl_module', $k, $v);
            }
        }

        //Send a notification
        $intNotificationId = $this->model->nc_notification;
        $objNotification = \NotificationCenter\Model\Notification::findByPk($intNotificationId);
        if (null !== $objNotification) {
            $objNotification->send($arrTokens, $objPage->language); // Language is optional
        }
    }

    //
}
