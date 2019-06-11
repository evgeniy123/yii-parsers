<?php

namespace backend\helpers;

use Yii;

class StringHelper
{
    public static function randomString($length = 15, $special = null, $directory = false)
    {
        $validCharacters = "abcdefghijklmnopqrstuxyvwzABCDEFGHIJKLMNOPQRSTUXYVWZ1234567890";
        $validCharacters_special = "abcdefghijklmnopqrstuxyvwzABCDEFGHIJKLMNOPQRSTUXYVWZ1234567890+-:;";


        $validCharacters = isset($special) ? $validCharacters : $validCharacters_special;
        $validCharacters = (!$directory) ? $validCharacters : 'abcdefghijklmnopqrstuxyvwz';

        $validCharNumber = strlen($validCharacters);

        $result = "";

        //

        for ($i = 0; $i < $length; $i++) {
            $index = mt_rand(0, $validCharNumber - 1);
            $result .= $validCharacters[$index];
        }
        return $result;
    }

    /**
     * @param $string
     * @return mixed
     */
    public static function slashEscape($string)
    {
        $search = "'";
        $replace = "\'";
        return str_replace($search, $replace, $string);
    }


    /**
     * @param null $url
     * @param $only2Slash
     * @return string
     */
    public static function getFileNameWith2Slash($url = null, $only2Slash = null)
    {
        return ($only2Slash != null)
            ? substr($url, 0, 2) . '/' . substr($url, 2, 2)
            : substr($url, 0, 2) . '/' . substr($url, 2, 2) . '/' . $url;
    }

}

