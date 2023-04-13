<?php

namespace BurkiSchererAG\BSNewsSubmit;


use Contao\Input;
use Contao\Config;
use Contao\FormTextArea; //as ContaoFormTextArea;

class FormTextAreaFEE extends FormTextArea
{

    public function __construct()
    {
        $this->preserveTags = true;
    }

    /**
     * Just don't do \StringUtil::specialchars or run htmlspecialchars()
     * to keep tinyMCE html intact
     * for the rest do as normal form FromTextArea
     */
    public function __get($strKey)
    {
        switch ($strKey) {
            case 'value':

                $allowTags = str_replace('<script>', '', Config::get('allowedTags'));
                $varValue = Input::stripTags($this->varValue, $allowTags);
                return str_replace('\n', "\n", $varValue);

                break;

            default:
                return parent::__get($strKey);
                break;
        }
    }
}
