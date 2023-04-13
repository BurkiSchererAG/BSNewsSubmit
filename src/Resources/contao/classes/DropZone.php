<?php


namespace BurkiSchererAG\BSNewsSubmit;

use Contao\Input;
use Contao\Config;
use Contao\System;
use Contao\FileUpload;
use Contao\StringUtil;
use BurkiSchererAG\BSNewsSubmit\FeeHelper;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provide methods to handle file uploads 
 */
class DropZone extends FileUpload
{

  public $pathUuid;

  private $request;

  /**
   * This construct to set pathUuid
   */
  public function __construct($pathUuid, Request $request)
  {
    $this->pathUuid = $pathUuid;
    $this->request = $request;
    System::loadLanguageFile('tl_files');
  }

  /**
   * Generate the markup for the DropZone uploader
   *
   * @return string
   */
  public function generateMarkup()
  {
    // Maximum file size in MB
    $intMaxSize = round(static::getMaxUploadSize() / 1024 / 1024);

    // String of accepted file extensions from Contao 
    $strAccepted = implode(',', array_map(static function ($a) {
      return '.' . $a;
    }, StringUtil::trimsplit(',', strtolower(Config::get('uploadTypes')))));

    // Add the scripts
    $GLOBALS['TL_CSS']['dz'] = 'assets/dropzone/css/dropzone.min.css';
    $GLOBALS['TL_JAVASCRIPT']['dz'] = 'assets/dropzone/js/dropzone.min.js';

    //Check if the field name is set
    // if(!$field && $field !== $this->strName ) {
    //   return 'Not allowed';
    // }


    //Get url id from $_GET variable
    $session = $this->request->getSession();
    $pid =  $session->get('newsId');
    $ele = $session->get("eleid");


    //This is default, which will be changed dynamically
    $intMaxFile = 20;

    // Generate the markup
    $return = '
  <input type="hidden" name="action" value="fileupload">
  <div class="fallback">
    <input type="file" name="' . $this->strName . '[]" class="tl_upload_field" multiple>
  </div>
  <div class="dropzone">
    <div class="dz-default dz-message">
      <span>' . $GLOBALS['TL_LANG']['tl_files']['dropzone'] . '</span>
    </div>
    <span class="dropzone-previews"></span>
  </div>
  <script>
    Dropzone.autoDiscover = false;
    $(document).ready(function(){
      
      //Reset dropzone
      $("#uploadForm").prop(\'dropzone\', null);
      var $fileCounter =  $("#' . $this->strName . '")

      new Dropzone("#uploadForm", {
        //url: window.location.href,
        url: "/file_selection",
        params: {
            eleid: "' . $ele . '",
            pid: "' . $pid . '",
            path: "' . $this->pathUuid . '"
        },
        paramName: "' . $this->strName . '",
        maxFiles: ' . $intMaxFile . ',
        maxFilesize: ' . $intMaxSize . ',
        acceptedFiles: "' . $strAccepted . '",
        timeout: 0,
        previewsContainer: ".dropzone-previews",
        clickable: ".dropzone",
        dictFileTooBig: ' . json_encode($GLOBALS['TL_LANG']['tl_files']['dropzoneFileTooBig']) . ',
        dictInvalidFileType: ' . json_encode($GLOBALS['TL_LANG']['tl_files']['dropzoneInvalidType']) . ',
        init: function() {
          this.options.maxFiles = $fileCounter.data("file_qouta");
        }
      }).on("addedfile", function() {
        $(".dz-message").css("display", "none");
      }).on("success", function(file, message) {
        //Update files in frontend after upload
        $fragment = $("<div>").append( $.parseHTML( message ) ).find("#' . $this->strName . '");
        $("#' . $this->strName . '").html($fragment.html());

        //Activate drag/select/count function
        activeMetaModelFileFunction("' . $this->strName . '");
      }).on("complete", function() {
      });
      $("div.tl_formbody_submit").css("display", "none");
    });
  </script>';

    if (isset($GLOBALS['TL_LANG']['tl_files']['fileupload'][1])) {
      $return .= '
  <p class="tl_help tl_tip">' . sprintf($GLOBALS['TL_LANG']['tl_files']['fileupload'][1], System::getReadableSize(static::getMaxUploadSize()), Config::get('gdMaxImgWidth') . 'x' . Config::get('gdMaxImgHeight')) . '</p>';
    }

    return $return;
  }
}

// class_alias(DropZone::class, 'DropZone');
