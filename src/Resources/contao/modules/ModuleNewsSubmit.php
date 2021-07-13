<?php

namespace BurkiSchererAG;

use Contao\Date;
use Contao\Dbafs;
use Contao\Input;
use Contao\Folder;
use Contao\Module;
use Contao\System;
use Contao\Message;
use Patchwork\Utf8;
use Contao\Database;
use Contao\NewsModel;
use Contao\Controller;
use Contao\FilesModel;
use Contao\StringUtil;
use Haste\Util\Format;
use Contao\MemberModel;
use Contao\FrontendUser;
use Contao\Model\Registry;
use Contao\BackendTemplate;
use Contao\CoreBundle\Exception\ResponseException;

/**
 * Class ModuleNewsSubmit
 */
class ModuleNewsSubmit extends Module
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_bs_submitnews';
    protected $strTable = 'tl_news';
    private $objNews;

    /**
     * Return a wildcard in the back end
     *
     * @return string
     */
    public function generate()
    {
        if (TL_MODE == 'BE') {
            /** @var BackendTemplate|object $objTemplate */
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['bs_NewsSubmit'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            /*
                If there is file upload in editable fields,
                make sure upload destination is set
            */
            $editableFields = StringUtil::deserialize($this->bsNewsSubmitEditable);
            if (
                sizeof(array_intersect(['singleSRC', 'enclosure'], $editableFields)) > 0 &&
                !$this->bsUploadDir
            ) {
                $objTemplate->link .= ' <span style="color: red">(Please set upload folder)</span>';
            }
            /* end upload folder check */

            return $objTemplate->parse();
        }


        return parent::generate();
    }

    /**
     * Generate the module
     */
    protected function compile()
    {
        global $objPage;

        if (FE_USER_LOGGED_IN) {
            $objUser = FrontendUser::getInstance();
            // dump($objUser->getData());
            // die;
        }
        $this->editable = StringUtil::deserialize($this->bsNewsSubmitEditable);

        System::loadLanguageFile($this->strTable, $objPage->language);
        Controller::loadDataContainer($this->strTable);


        $this->Template->fields = '';
        $this->Template->tableless = $this->tableless;

        $objCaptcha = null;
        $doNotSubmit = false;
        $hasUpload   = false;
        $row         = 0;
        $max_row = count($this->editable);
        $strFormId = $this->strTable . '_' . $this->id;

        //This is set early, because its needed in function to create upload folder
        $this->Template->formId  =  $strFormId;

        $this->objNews = new NewsModel();
        
        // Captcha, Check Captcha early, as creating upload folder depend on errors
        // but add to Widget/FFL at the end
        if (!$this->disableCaptcha) {
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

            //This is required here.
            $this->Template->hasError = $doNotSubmit;
        }

        // Build the form
        foreach ($this->editable as $field) {
            $arrData = &$GLOBALS['TL_DCA'][$this->strTable]['fields'][$field];

            // Map checkboxWizards to regular checkbox widgets
            if (($arrData['inputType'] ?? null) == 'checkboxWizard') {
                $arrData['inputType'] = 'checkbox';
            }

            // Map fileTrees to upload widgets (see #8091)
            if (($arrData['inputType'] ?? null) == 'fileTree') {
                $arrData['inputType'] = 'upload';

                //Create custom folder only when form is submitted and doNotSubmit flag is false
                //to avoid increasing news id. It is better if the upload field is kept at the end of form,
                //so that all other fields are validated before creating the folder.
                if (!$doNotSubmit && Input::post('FORM_SUBMIT') == $strFormId && $arrData['eval']['storeFile']) {
                    //Set custom upload folder
                    $arrData['eval']['uploadFolder'] = $this->getUploadFolderUuid();

                    if (FE_USER_LOGGED_IN && $objUser->assignDir && $objUser->homeDir) {
                        $arrData['eval']['uploadFolder'] = $objUser->homeDir;
                    }
                }
            }

            /** @var \Widget $strClass */
            $strClass = $GLOBALS['TL_FFL'][$arrData['inputType']] ?? null;

            // Continue if the class does not exist
            if (!$arrData['eval']['feEditable'] || !class_exists($strClass)) {
                --$max_row;
                continue;
            }

            $arrData['eval']['required']    = $arrData['eval']['mandatory'] ?? null;
            $arrData['eval']['tableless']   = $this->tableless;
            $arrData['eval']['placeholder'] = $arrData['label'][0];

            $varValue = '';

            //Make Frontend Form fields from dca widget
            $objWidget = new $strClass($strClass::getAttributesFromDca($arrData, $field, $varValue, $field, $this->strTable, $this));

            // Append the module ID to prevent duplicate IDs (see #1493)
            $objWidget->id .= '_' . $this->id;
            $objWidget->storeValues = true;
            $objWidget->rowClass = 'row_' . $row . (($row == 0) ? ' row_first' : (($row == ($max_row - 1)) ? ' row_last' : '')) . ((($row % 2) == 0) ? ' even' : ' odd');

            $objWidget->class .= ' ' . $objWidget->rowClass;

            if ($objWidget instanceof \uploadable) {
                $hasUpload = true;
            }

            // Validate the form data, if doNotSubmit flag is not set
            if (!$doNotSubmit && Input::post('FORM_SUBMIT') == $strFormId) {
                $objWidget->validate();

                $varValue = $objWidget->value;

                $rgxp = $arrData['eval']['rgxp'];

                // Convert date formats into timestamps (check the eval setting first -> #3063)
                if ($varValue !== null && $varValue !== '' && \in_array($rgxp, ['date', 'time', 'datim'])) {
                    try {
                        $objDate = new Date($varValue, Date::getFormatFromRgxp($rgxp));
                        $varValue = $objDate->tstamp;
                    } catch (\OutOfBoundsException $e) {
                        $objWidget->addError(sprintf($GLOBALS['TL_LANG']['ERR']['invalidDate'], $varValue));
                    }
                }

                if ($varValue !== null && $varValue !== '' && $rgxp == 'url') {
                    //Copy from Contao 4.11 Widget/HttpUrlListener.php + Check domain name
                    if (!preg_match('~^https?://~i', $varValue) && !filter_var($varValue, FILTER_VALIDATE_URL)) {
                        $objWidget->class = 'error';
                        $objWidget->addError($GLOBALS['TL_LANG']['ERR']['invalidHttpUrl']);
                    }
                }

                // Trigger the save_callback (see #5247)
                if (\is_array($arrData['save_callback'] ?? null) && $objWidget->submitInput() && !$objWidget->hasErrors()) {
                    foreach ($arrData['save_callback'] as $callback) {
                        try {
                            if (\is_array($callback)) {
                                $this->import($callback[0]);
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
                    // Store the form data
                    $_SESSION['FORM_DATA'][$field] = $varValue;

                    // Set the correct empty value (see #6284, #6373)
                    if ($varValue === '') {
                        $varValue = $objWidget->getEmptyValue();
                    }

                    // Set the new value
                    $this->objNews->$field = $varValue;
                }
            }

            $temp = $objWidget->parse();

            $this->Template->fields .= $temp;

            ++$row;
        }


        // Add Captcha at the end
        if (!$this->disableCaptcha) {
            //Parse captcha and add to fields
            $strCaptcha = $objCaptcha->parse();
            $this->Template->fields .= $strCaptcha;
        }

        //Add news creator member for logged in
        if (FE_USER_LOGGED_IN && $objUser->id) {
            $this->objNews->member = $objUser->id;
        }

        $this->Template->hasError = $doNotSubmit;
        $this->Template->messages = Message::generate();
        $this->Template->slabel  = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['saveData']);
        $this->Template->enctype = $hasUpload ? 'multipart/form-data' : 'application/x-www-form-urlencoded';
        $this->Template->action  =  '{{env::request}}';

        // Redirect or reload if there was no error
        if (Input::post('FORM_SUBMIT') == $strFormId && !$doNotSubmit) {

            //Create News
            $this->createNews();

            // Check whether there is a jumpTo page
            if (($objJumpTo = $this->objModel->getRelated('jumpTo')) !== null) {
                $this->jumpToOrReload($objJumpTo->row());
            }

            Message::addConfirmation($GLOBALS['TL_LANG']['MSC']['savedData']);
            $this->reload();
        }
    }


    /**
     * create a new News
     */
    public function createNews()
    {
        $this->objNews->tstamp  = time();
        $this->objNews->date = time();
        $this->objNews->time = time();

        $arrAttachtment = []; //Add file path to notifcation later on
        $contentElement = [];
        $slug_seed = $this->objNews->headline ?: 'newsurl';

        //news Archive
        $this->objNews->pid     = $this->bsNewsSubmitArchive;
        $newsArchive = $this->objNews->getRelated('pid')->row();
        $this->objNews->author = $newsArchive['newsOwner'];

        //Generate alias
        $slugOptions = $newsArchive['jumpTo'];
        $aliasExists = function (string $alias): bool {
            return $this->Database->prepare("SELECT id FROM $this->strTable WHERE alias=?")->execute($alias)->numRows > 0;
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
            foreach (\array_keys($_SESSION['FILES']) as $fieldName) {
                //enclosure; check also session file key is in the editable list
                if (\in_array($fieldName, $this->editable) && $fieldName == 'enclosure') {
                    $this->objNews->addEnclosure = 1;
                    $this->objNews->{$fieldName} = $_SESSION['FILES'][$fieldName]['uuid'];
                    $arrAttachtment[$fieldName] = str_replace([TL_ROOT, ' '], ['{{env::url}}', '%20'], $_SESSION['FILES'][$fieldName]['tmp_name']);
                }

                //Teaser singleSRC
                if (\in_array($fieldName, $this->editable) && $fieldName == 'singleSRC') {
                    $this->objNews->addImage = 1;
                    $imgFileModel = FilesModel::findByUuid($_SESSION['FILES'][$fieldName]['uuid']);
                    $this->objNews->{$fieldName} = $imgFileModel->uuid;
                    $arrAttachtment['teaser_image'] = str_replace([TL_ROOT, ' '], ['{{env::url}}', '%20'], $_SESSION['FILES'][$fieldName]['tmp_name']);
                }
            }
        }

        //Store if there is any detail text to create content element
        $contentElement = array_filter($this->objNews->row(), function ($key) {
            return strpos($key, 'detailCE') === 0;
        }, ARRAY_FILTER_USE_KEY);

        // dd($contentElement);
        $objNewNews = $this->objNews->save();

        if ($objNewNews !== null) {
            //Create content elements if any
            if (count($contentElement)) {
                
                //make into numeric array
                $contentElement = array_values($contentElement);

                foreach ($contentElement as $index => $element) {
                    if (strlen(trim($element)) < 1) {
                        continue;
                    }

                    $ce_text['pid'] = $objNewNews->id;
                    $ce_text['ptable'] = $this->strTable;
                    $ce_text['type'] = 'text';
                    $ce_text['sorting'] = '100' . $index * 10;
                    $ce_text['tstamp'] = time();
                    $ce_text['text'] = $element;
                    Database::getInstance()->prepare("INSERT INTO tl_content %s")->set($ce_text)->execute();
                }
            }

            //Add pseudo property to objNewNews, to store file path information for Notification
            //which you can access as ##{fieldName}_path## in notification
            if (count($arrAttachtment)) {
                foreach ($arrAttachtment as $key => $filePath) {
                    $objNewNews->{$key . '_path'} = $filePath;
                }
            }

            $this->sendNotification($objNewNews);
        }
    }



    /**
     * Send Notification Email
     */
    public function sendNotification($objNewNews)
    {
        global $objPage;
        $arrMember = [];
        $arrTokens = [];

        if (FE_USER_LOGGED_IN) {
            $objMember = FrontendUser::getInstance();
            $arrMember = $objMember->getData();
        }

        if (!FE_USER_LOGGED_IN) {

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
        }

        //Add Module data
        foreach ($this->arrData as $k => $v) {
            if (!\is_object($v)) {
                $arrTokens['newssubmit_mod_' . $k] = Format::dcaValue('tl_module', $k, $v);
            }
        }

        //Send a notification
        $intNotificationId = $this->nc_notification;
        $objNotification = \NotificationCenter\Model\Notification::findByPk($intNotificationId);
        if (null !== $objNotification) {
            $objNotification->send($arrTokens, $objPage->language); // Language is optional
        }
    }



    /**
     * Set UploadFolder and return its Uuid
     * @return uuid
     */
    public function getUploadFolderUuid()
    {
        $uuid = $this->bsUploadDir;

        $this->objNews->tstamp  = time();
        $this->news = $this->objNews->save();
        // Registry::getInstance()->register($this->objNews);

        // Create new folder only when the form is sumbitted without error
        if ($GLOBALS['BS_NewsSubmit']['BS_CUSTOM_FOLDER'] && Input::post('FORM_SUBMIT') == $this->Template->formId && !$this->Template->hasError) {
            if ($this->bsUploadDir) {
                $basePath = FilesModel::findById($uuid)->row()['path'];
            } else {
                $basePath = 'files';
            }


            //If there is custom logic define then use that.
            if (\is_callable($GLOBALS['BS_NewsSubmit']['BS_CUSTOM_FOLDER_FUNCTION'])) {
                return $GLOBALS['BS_NewsSubmit']['BS_CUSTOM_FOLDER_FUNCTION']($this, $basePath);
            }

            //You can add any logic by defining callback function $GLOBALS['BS_NewsSubmit']['BS_CUSTOM_FOLDER_FUNCTION']
            $newFolder = date('Ymd-Hi') . '-' . $this->news->id;

            $objFolder = new Folder($basePath . '/' . $newFolder);

            if (($uuid = $objFolder->getModel()->uuid) == null) {
                //We fall here if the folder is excluded from the DBAFS
                $fileModel = Dbafs::addResource($objFolder->path);
                $uuid = $fileModel->row()['uuid'];
            }
        }

        return $uuid;
    }
}
