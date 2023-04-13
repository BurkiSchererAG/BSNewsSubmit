<?php


namespace BurkiSchererAG\BSNewsSubmit;

use Contao\FormFileUpload;

class FormFileUploadFEE extends FormFileUpload
{
    protected $strTemplate = 'bs_form_upload';
}

/**
 * This class is used for single file upload,
 * It is extended here just to make use of another template.
 */
