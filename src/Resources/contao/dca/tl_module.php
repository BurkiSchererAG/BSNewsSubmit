<?php

use Contao\System;
use Contao\Controller;

// Fields

$GLOBALS['TL_DCA']['tl_module']['fields']['bsNewsSubmitEditable'] = [
    'label'                   => &$GLOBALS['TL_LANG']['tl_module']['bsNewsSubmitEditable'],
    'exclude'                 => true,
    'inputType'               => 'checkboxWizard',
    'options_callback'        => static function () {
        $return = [];

        System::loadLanguageFile('tl_news');
        Controller::loadDataContainer('tl_news');

        foreach ($GLOBALS['TL_DCA']['tl_news']['fields'] as $k => $v) {
            if (isset($v['eval']['feEditable']) && $v['eval']['feEditable']) {
                if (strlen($GLOBALS['TL_DCA']['tl_news']['fields'][$k]['label'][0] ?? '') > 0) {
                    $return[$k] = $GLOBALS['TL_DCA']['tl_news']['fields'][$k]['label'][0];
                } else {
                    $return[$k] = $k;
                }
            }
        }

        return $return;
    },
    'eval'                    => ['multiple' => true, 'submitOnChange' => true],
    'sql'                     => "blob NULL"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['bsNewsSubmitArchive'] = [
    'label'                   => &$GLOBALS['TL_LANG']['tl_module']['bsNewsSubmitArchive'],
    'exclude'                 => true,
    'inputType'               => 'select',
    'foreignKey'              => 'tl_news_archive.title',
    'eval'                    => ['chosen' => true],
    'sql'                     => "int(10) unsigned NOT NULL default '0'"
];


$GLOBALS['TL_DCA']['tl_module']['fields']['bsUploadDir'] = [
    'label'                   => &$GLOBALS['TL_LANG']['tl_module']['bsUploadDir'],
    'exclude'                 => true,
    'inputType'               => 'fileTree',
    'eval'                    => ['fieldType' => 'radio', 'mandatory' => true, 'tl_class' => 'clr'],
    'sql'                     => "binary(16) NULL"
];

//This field may have been already added from BSModule
$GLOBALS['TL_DCA']['tl_module']['fields']['bs_checkbox'] = array(
    'exclude'                 => true,
    'inputType'               => 'checkbox',
    'eval'                    => array('tl_class' => 'w50'),
    'sql'                     => "char(1) NOT NULL default ''"
);


/**
 * Add fields to the pallette
 */
$GLOBALS['TL_DCA']['tl_module']['palettes']['bs_NewsSubmit'] = '{title_legend},name,headline,type;
                                                                {config_legend},bsNewsSubmitArchive,bsNewsSubmitEditable,disableCaptcha,bsUploadDir,bs_checkbox; 
                                                                {notification_legend},nc_notification;                                                               
                                                                {redirect_legend},jumpTo;{template_legend:hide},tableless;
                                                                {protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';

/**
 * Notification choices
 */
$GLOBALS['TL_DCA']['tl_module']['fields']['nc_notification']['eval']['ncNotificationChoices']['bs_NewsSubmit'] = ['bs_NewsSubmit'];
