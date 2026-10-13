<?php

namespace addons\gamerank\service;

class Text
{
    public static function length($value)
    {
        $value = (string)$value;
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }
        if (function_exists('iconv_strlen')) {
            $length = iconv_strlen($value, 'UTF-8');
            if ($length !== false) {
                return $length;
            }
        }
        return strlen($value);
    }

    public static function slice($value, $start, $length)
    {
        $value = (string)$value;
        if (function_exists('mb_substr')) {
            return mb_substr($value, $start, $length, 'UTF-8');
        }
        if (function_exists('iconv_substr')) {
            $result = iconv_substr($value, $start, $length, 'UTF-8');
            if ($result !== false) {
                return $result;
            }
        }
        return substr($value, $start, $length);
    }
}
