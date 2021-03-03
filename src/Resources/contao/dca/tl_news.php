<?php

use Contao\System;
use Contao\Controller;
use Contao\StringUtil;
use Contao\Image;
use Contao\FrontendUser;
use Contao\CoreBundle\DataContainer\PaletteManipulator;


System::loadLanguageFile('tl_news');
System::loadLanguageFile('tl_member');
Controller::loadDataContainer('tl_news');

/* mark these standard fields as editable */
foreach (array('headline', 'teaser', 'location', 'url', 'singleSRC', 'enclosure') as $key) {
    $GLOBALS['TL_DCA']['tl_news']['fields'][$key]['eval']['feEditable'] = true;
}

/* Change some setting of standard fields, when in Frontend */
if (TL_MODE == 'FE') {
    $GLOBALS['TL_DCA']['tl_news']['fields']['endDate']['eval']['mandatory']   = false;
    $GLOBALS['TL_DCA']['tl_news']['fields']['startTime']['eval']['mandatory'] = false;
    $GLOBALS['TL_DCA']['tl_news']['fields']['endTime']['eval']['mandatory']   = false;
    $GLOBALS['TL_DCA']['tl_news']['fields']['teaser']['eval']['mandatory']   = true;

    $GLOBALS['TL_DCA']['tl_news']['fields']['url']['eval']['mandatory']   = false;

    //enclosure
    $GLOBALS['TL_DCA']['tl_news']['fields']['enclosure']['eval']['storeFile']   = true;
    $GLOBALS['TL_DCA']['tl_news']['fields']['enclosure']['eval']['mandatory']   = false;

    //teaser image
    $GLOBALS['TL_DCA']['tl_news']['fields']['singleSRC']['eval']['storeFile']   = true;
    $GLOBALS['TL_DCA']['tl_news']['fields']['singleSRC']['eval']['mandatory']   = false;
    $GLOBALS['TL_DCA']['tl_news']['fields']['singleSRC']['label'] = &$GLOBALS['TL_LANG']['tl_news']['singleSRC'];


    /**
     * These save_callback are only relevant from backend and also it expects DataContainer $dc as 2nd argument.
     * In the frontend we set the correct values from our script, hence unset it
     */
    unset($GLOBALS['TL_DCA']['tl_news']['fields']['endTime']['save_callback']);
    unset($GLOBALS['TL_DCA']['tl_news']['fields']['endDate']['save_callback']);
}

/**
 * Custom Fields
 */
$GLOBALS['TL_DCA']['tl_news']['fields']['email'] = array(
    'label'                   => &$GLOBALS['TL_LANG']['tl_news']['email'],
    'exclude'                 => true,
    'search'                  => true,
    'filter'                  => true,
    'sorting'                 => true,
    'inputType'               => 'text',
    'eval'                    => array('maxlength' => 255, 'rgxp' => 'email', 'decodeEntities' => true, 'feEditable' => true, 'feViewable' => true, 'tl_class' => 'w50'),
    'sql'                     => "varchar(255) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_news']['fields']['company'] = array(
    'label'                   => &$GLOBALS['TL_LANG']['tl_news']['company'],
    'exclude'                 => true,
    'search'                  => true,
    'filter'                  => true,
    'sorting'                 => true,
    'inputType'               => 'text',
    'eval'                    => array('maxlength' => 255, 'decodeEntities' => true, 'feEditable' => true, 'feViewable' => true, 'tl_class' => 'w50'),
    'sql'                     => "varchar(255) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_news']['fields']['designation'] = array(
    'label'                   => &$GLOBALS['TL_LANG']['tl_news']['designation'],
    'exclude'                 => true,
    'search'                  => true,
    'sorting'                 => true,
    'flag'                    => 1,
    'inputType'               => 'text',
    'eval'                    => array('maxlength' => 255, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'career', 'tl_class' => 'w50'),
    'sql'                     => "varchar(255) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_news']['fields']['firstname'] = array(
    'label'                   => &$GLOBALS['TL_LANG']['tl_news']['firstname'],
    'exclude'                 => true,
    'search'                  => true,
    'sorting'                 => true,
    'flag'                    => 1,
    'inputType'               => 'text',
    'eval'                    => array('maxlength' => 255, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'personal', 'tl_class' => 'w50'),
    'sql'                     => "varchar(255) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_news']['fields']['lastname'] = array(
    'label'                   => &$GLOBALS['TL_LANG']['tl_news']['lastname'],
    'exclude'                 => true,
    'search'                  => true,
    'sorting'                 => true,
    'flag'                    => 1,
    'inputType'               => 'text',
    'eval'                    => array('maxlength' => 255, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'personal', 'tl_class' => 'w50'),
    'sql'                     => "varchar(255) NOT NULL default ''"
);


$GLOBALS['TL_DCA']['tl_news']['fields']['member'] = array(
    'label'                   => &$GLOBALS['TL_LANG']['tl_news']['member'],
    'exclude'                 => true,
    'search'                  => true,
    'filter'                  => true,
    'sorting'                 => true,
    'flag'                    => 11,
    'inputType'               => 'select',
    'foreignKey'              => 'tl_member.lastname',
    'eval'                    => array('doNotCopy' => true, 'chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'clr w50 wizard'),
    'wizard' => [
        static function (\Contao\DataContainer $dc) {
            return ($dc->value < 1) ? '' :
                ' <a href="contao/main.php?do=member&amp;act=edit&amp;id=' . $dc->value .
                '&amp;popup=1&amp;nb=1&amp;rt=' . REQUEST_TOKEN . '" title="' .
                sprintf(StringUtil::specialchars($GLOBALS['TL_LANG']['tl_news']['memberinfo']), $dc->value) .
                '" onclick="Backend.openModalIframe({\'title\':\''  .
                StringUtil::specialchars(str_replace("'", "\\'", sprintf($GLOBALS['TL_LANG']['tl_news']['memberinfo'], $dc->value))) .
                '\',\'url\':this.href});return false">' .
                Image::getHtml('alias.gif') .
                '</a>';
        }
    ],
    'sql'                     => "int(10) unsigned NOT NULL default '0'",
    'relation'                => array('type' => 'hasOne', 'load' => 'eager')
);


//This doesn't have real DB field. Its used add details input textarea

foreach (range(1, $GLOBALS['BS_NewsSubmit']['DETAIL_CE_TEXT_FIELD']) as $key) {
    $GLOBALS['TL_DCA']['tl_news']['fields']['detailCE_' . $key] = array(
        'label'                   => &$GLOBALS['TL_LANG']['tl_news']['detailCE' . $key],
        'exclude'                 => true,
        'inputType'               => 'textarea',
        'eval'                    => array('rte' => 'tinyMCE', 'decodeEntities' => true, 'feEditable' => true),
    );
}

/**
 * Show or hide personal fields depending upon if user is logged in or guest.
 **/
if (TL_MODE == 'FE') {
    $objFrontendUser = FrontendUser::getInstance();

    $arrGuestFields = array('email', 'firstname', 'lastname', 'designation', 'company');

    if ($objFrontendUser->email === null) {
        foreach ($arrGuestFields as $key) {
            $GLOBALS['TL_DCA']['tl_news']['fields'][$key]['eval']['mandatory'] = true;
        }
    } else {
        foreach ($arrGuestFields as $key) {
            $GLOBALS['TL_DCA']['tl_news']['fields'][$key]['eval']['feEditable'] = false;
        }
    }
}

/**
 * Add Palette
 */
$pm = PaletteManipulator::create()
    ->addLegend('creator_legend', 'title_legend', PaletteManipulator::POSITION_AFTER)
    ->addField('member, email, firstname, lastname, designation, company', 'creator_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_news')
    ->applyToPalette('internal', 'tl_news')
    ->applyToPalette('article', 'tl_news')
    ->applyToPalette('external', 'tl_news');
