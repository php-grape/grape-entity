<?php

namespace PhpGrape;

class Reflection
{
    private static array $cacheProps = [];
    private static array $cacheMthds = [];

    public static bool $disableProtectedProps = false;
    public static bool $disablePrivateProps = false;
    public static bool $disableProtectedMethods = false;
    public static bool $disablePrivateMethods = false;

    public static function resetCache()
    {
        self::$cacheProps = [];
        self::$cacheMthds = [];
    }

    public static function getProperty(string $class, string $prop)
    {
        if (!isset(self::$cacheProps[$class][$prop])) {
            if (!isset(self::$cacheProps[$class])) self::$cacheProps[$class] = [];
            $property = new \ReflectionProperty($class, $prop);
            if (
                $property->isStatic() ||
                (
                    (self::$disablePrivateProps === \true && $property->isPrivate()) ||
                    (self::$disableProtectedProps === \true && $property->isProtected())
                )
            )
                return (self::$cacheProps[$class][$prop] = \false);
            $property->setAccessible(\true);
            return (self::$cacheProps[$class][$prop] = $property);
        }
        return self::$cacheProps[$class][$prop];
    }

    public static function getMethod(string $class, string $name)
    {
        if (!isset(self::$cacheMthds[$class][$name])) {
            if (!isset(self::$cacheMthds[$class])) self::$cacheMthds[$class] = [];
            $method = new \ReflectionMethod($class, $name);
            if (
                $method->isStatic() || $method->isAbstract() || $method->getNumberOfRequiredParameters() !== 0 ||
                (
                    (self::$disablePrivateMethods === \true && $method->isPrivate()) ||
                    (self::$disableProtectedMethods === \true && $method->isProtected())
                )
            )
                return (self::$cacheMthds[$class][$name] = \false);
            $method->setAccessible(\true);
            return (self::$cacheMthds[$class][$name] = $method);
        }
        return self::$cacheMthds[$class][$name];
    }
}
