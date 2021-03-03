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



foreach (range(1, $GLOBALS['BS_newsSubmit']['DETAIL_CE_TEXT_FIELD']) as $key) {
    $GLOBALS['TL_LANG']['tl_news']['detailCE' . $key] = ['Add detail news textarea-' . $key, 'Add detail input textarea'];
}
/**
 * Legend
 */
$GLOBALS['TL_LANG']['tl_news']['creator_legend'] = 'News Creator Information';
