<?php

/**
 * Front end modules
 */
// $GLOBALS['FE_MOD']['bs']['bs_NewsSubmit'] = 'BurkiSchererAG\ModuleNewsSubmit';


/**
 * Notification Center Notification Types
 */
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['bs']['bs_NewsSubmit']  = [
    'email_text' => [
        'newssubmit_mod_*', 'news_*', 'member_*',
        'GuestCompany', 'GuestTitle', 'GuestFirstname', 'GuestLastname', 'GuestEmail'
    ],
    'file_name' => [
        'newssubmit_mod_*', 'news_*', 'member_*'
    ]
];


/* make same variables from email_text above, avialable to email_subject, email_html and file_content */
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['bs']['bs_NewsSubmit']['email_subject'] =
    &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['bs']['bs_NewsSubmit']['email_text'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['bs']['bs_NewsSubmit']['email_html'] =
    &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['bs']['bs_NewsSubmit']['email_text'];
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['bs']['bs_NewsSubmit']['file_content'] =
    &$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['bs']['bs_NewsSubmit']['email_text'];



/**
 * Some Configuration values
 */
//If there are uploads then create a destination subfolder automatically inside the base folder.
//Subfolder name is YYYYMMDD-HHMM-NewsID. Set to false if you like to have all files inside the base folder
$GLOBALS['bs_NewsSubmit']['BS_CUSTOM_FOLDER'] = true;

//If you prefer to have another naming for the subfolder, then define a clouser function like example give below
$GLOBALS['bs_NewsSubmit']['BS_CUSTOM_FOLDER_FUNCTION'] = null;

//Adds detail textarea
$GLOBALS['bs_NewsSubmit']['DETAIL_CE_TEXT_FIELD'] = 1;

//maxAllowedFileCount
$GLOBALS['bs_NewsSubmit']['maxAllowedFileCount'] = 10;



$GLOBALS['TL_CSS']['newsFee'] =  '/bundles/bsnewssubmit/css/general.css';

/**
 * Example folder name callback
 */
 /*
$GLOBALS['bs_NewsSubmit']['BS_CUSTOM_FOLDER_FUNCTION'] =  function ($obj, $basePath) {

    //You can add any logic here
    $newFolder = rand(0, 100);

    $objFolder = new \Contao\Folder($basePath . '/' . $newFolder);

    if (($uuid = $objFolder->getModel()->uuid) == null) {
        //We fall here if the folder is excluded from the DBAFS
        $fileModel = \Contao\Dbafs::addResource($objFolder->path);
        $uuid = $fileModel->row()['uuid'];
    }

    return $uuid;
};
 */
