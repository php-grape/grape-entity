<?php

namespace PhpGrape;


class Util
{
    private static array $camelCache = [];
    private static array $snakeCache = [];

    public static function camel(string $str): string
    {
        if (isset(self::$camelCache[$str])) return self::$camelCache[$str];
        return self::$camelCache[$str] = \preg_replace_callback('/^[-_\s]+|[-_\s]+(.)?/u', function ($match) {
            return isset($match[1]) ? \mb_strtoupper($match[1], 'UTF-8') : '';
        }, \mb_strtolower($str, 'UTF-8'));
    }


    public static function snake(string $str): string
    {
        if (isset(self::$snakeCache[$str])) return self::$snakeCache[$str];
        return self::$snakeCache[$str] = \mb_strtolower(\preg_replace_callback('/(.)(\p{Lu})|(\s+(.)?)/u', function ($match) {
            return isset($match[3]) ? (isset($match[4]) ? $match[4] : '') : $match[1] . '_' . $match[2];
        }, $str), 'UTF-8');
    }

    public static function is_assoc(iterable $arr): bool
    {
        $i = 0;
        foreach ($arr as $k => $v) if ($i++ !== $k) return \true;
        return \false;
    }

    private static function setXMLFromArray(array $arr, \SimpleXMLElement &$xml)
    {
        foreach ($arr as $key => $value) {
            if (\is_array($value)) {
                if (is_numeric($key)) {
                    self::setXMLFromArray($value, $xml);
                } else {
                    $node = $xml->addChild($key);
                    self::setXMLFromArray($value, $node);
                }
            } else {
                $xml->addChild("$key", htmlspecialchars("$value"));
            }
        }
    }

    public static function arrayToXML(array $arr, array $options = []): string
    {
        $options = array_merge(['root' => 'root', 'encoding' => 'UTF-8'], $options);
        $encoding = $options['encoding'] === null ? '' : " encoding=\"{$options['encoding']}\"";
        $xml = new \SimpleXMLElement("<?xml version=\"1.0\"$encoding?><{$options['root']}></{$options['root']}>");
        self::setXMLFromArray($arr, $xml);
        return $xml->asXML();
    }
}
