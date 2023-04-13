<?php
//Rename core
$GLOBALS['TL_LANG']['tl_news_archive']['author'][0] = 'Author/Contao User';


/**
 * Fields
 */
$GLOBALS['TL_LANG']['tl_news']['designation'] = ['Salutation', 'Salutation or Job title'];
$GLOBALS['TL_LANG']['tl_news']['member'] = ['News creator / Member', 'Select the member who create this News'];

$GLOBALS['TL_LANG']['tl_news']['email'] = ['News creator email', 'Email of person who created this News'];
$GLOBALS['TL_LANG']['tl_news']['firstname'] = ['First name', 'First name of person who created this News'];
$GLOBALS['TL_LANG']['tl_news']['lastname'] = ['Last name', 'First name of person who created this News'];
$GLOBALS['TL_LANG']['tl_news']['company'] = ['Company/Organization', 'Company of person who created this News'];
$GLOBALS['TL_LANG']['tl_news']['singleSRC'] = ['Teaser Image', 'Please select image file'];
$GLOBALS['TL_LANG']['tl_news']['phone'] = ['Phone', 'Phone'];
$GLOBALS['TL_LANG']['tl_news']['location'] = ['Location', 'Location'];

if (isset($GLOBALS['bs_NewsSubmit']['DETAIL_CE_TEXT_FIELD'])) {
    foreach (range(1, $GLOBALS['bs_NewsSubmit']['DETAIL_CE_TEXT_FIELD']) as $key) {
        $GLOBALS['TL_LANG']['tl_news']['detailCE_' . $key] = ['Add news details, Textarea-' . $key, 'Add detail input textarea'];
    }
}

$GLOBALS['TL_LANG']['tl_news']['image_gallery'] = ['Image Gallery', 'Add additional images'];

/**
 * Legend
 */
$GLOBALS['TL_LANG']['tl_news']['creator_legend'] = 'News Creator Information';
