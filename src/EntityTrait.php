<?php

namespace PhpGrape;

use PhpGrape\Exceptions\InvalidOptionException;
use PhpGrape\Exceptions\InvalidTypeException;
use PhpGrape\Exceptions\NestedExposureException;


trait EntityTrait
{
    private static array $ALLOWED_OPTIONS = [
        'serializable',
        'as',
        'default',
        'expose_null',
        'using',
        'override',
        'if',
        'safe',
        'merge',
        'format_with',
        'attr_path',
        'documentation'
    ];

    public static array $rootExposures = [];
    public static array $formatters = [];

    private static bool $initialized = \false;
    private static int $countNestedExposures = 0;
    private static array $defaultOptions = [];
    private static ?array $documentation = \null;
    private static bool $presentCollection = \false;
    private static string $collectionName = 'items';
    private static ?string $collectionRoot = \null;
    private static ?string $root = \null;

    public static function load()
    {
        if (!self::$initialized) {
            self::$initialized = \true;
            self::initialize();
        }
    }

    public abstract static function initialize();

    public static function extends(): void
    {
        $args = \func_get_args();
        foreach ($args as $class) {
            $class::load();
            self::$rootExposures = array_merge(self::$rootExposures, $class::$rootExposures);
            self::$formatters = array_merge(self::$formatters, $class::$formatters);
        }
    }

    private static function &exposures(): ?array
    {
        return self::$rootExposures;
    }

    public static function unexpose(): void
    {
        if (self::$countNestedExposures !== 0) {
            throw new NestedExposureException('You cannot call `unexpose` inside of nesting exposure!');
        }
        self::$documentation = \null;
        $props = \func_get_args();
        self::$rootExposures = array_filter(self::$rootExposures, function ($item) use ($props) {
            return !\in_array($item['prop'], $props);
        });
    }

    public static function unexposeAll(): void
    {
        if (self::$countNestedExposures !== 0) {
            throw new NestedExposureException('You cannot call `unexpose` inside of nesting exposure!');
        }
        self::$documentation = \null;
        self::$rootExposures = [];
    }

    private static function checkOptions(array $options): void
    {
        $invalidOptions = \array_values(\array_diff(\array_keys($options), self::$ALLOWED_OPTIONS));
        if ($invalidOptions)
            throw new InvalidOptionException('Unrecognized `' . $invalidOptions[0] . '` option');
    }

    public static function documentation(): array
    {
        if (self::$documentation === \null) {
            self::$documentation = [];
            self::load();
            $entityClass = \get_called_class();
            foreach (self::$rootExposures as $exposure) {
                $exposureOptions = $exposure['options'];
                if (!empty($exposureOptions['documentation'])) {
                    if (!empty($exposureOptions['as'])) {
                        if ($exposureOptions['as'] instanceof \Closure)
                            throw new InvalidTypeException('`documentation` does not support `as` option as closure');
                        $key = $exposureOptions['as'];
                    } else {
                        $key = $exposure['prop'];
                    }
                    if (isset($entityClass::$keyTransformer)) $key = ($entityClass::$keyTransformer)($key);
                    self::$documentation[$key] = $exposureOptions['documentation'];
                }
            }
        }
        return self::$documentation;
    }

    public static function expose(string $prop): void
    {
        $args = \func_get_args();
        $count = \func_num_args();
        $options = \null;
        $func = \null;

        if ($args[$count - 1] instanceof \Closure) {
            --$count;
            $func = \array_pop($args);
        }
        if (\is_array($args[$count - 1])) {
            --$count;
            $options = \array_pop($args);
        }
        $options = ((array) $options) + self::$defaultOptions;

        // Alias
        if (isset($options['with'])) {
            $options['using'] = $options['with'];
            unset($options['with']);
        }
        // Func can also be passed as an option (useful to use with `withOptions`)
        if (isset($options['func']) && $func === \null) {
            $func = $options['func'];
            unset($options['func']);
        }
        // This way we can check if the option is set with isset
        if (\array_key_exists('attr_path', $options) && $options['attr_path'] === \null)
            $options['attr_path'] = false;

        if ($count > 1) { // Multi props
            if ($func !== \null) throw new InvalidOptionException('You may not use a `function` on multi-attribute exposures');
            if (\array_key_exists('expose_null', $options)) throw new InvalidOptionException('You may not use `expose_null` on multi-attribute exposures');
            if (isset($options['as'])) throw new InvalidOptionException('You may not use the `as` option on multi-attribute exposures');
            foreach ($args as $arg)
                \call_user_func_array(\get_called_class() . '::expose', \array_filter([$arg, $options, $func], function ($item) {
                    return $item !== \null;
                }));
            return;
        }
        $value = ['prop' => $prop, 'options' => $options];
        if ($func !== \null) $value['func'] = $func;

        self::checkOptions($options);
        self::$documentation = \null;

        if (isset($options['override']) && $options['override'] === \true) {
            self::$rootExposures = \array_filter(self::$rootExposures, function ($item) use ($prop) {
                return $prop !== $item['prop'];
            });
        }

        if ($func !== \null && (new \ReflectionFunction($func))->getNumberOfParameters() === 0) {
            if ($options !== \null) {
                if (isset($options['format_with'])) throw new InvalidOptionException('You may not use the `format_with` option on nested exposure');
            }

            unset($value['func']);
            $value['exposures'] = [];

            $parentRepresentation = &self::exposures();
            self::$rootExposures = &$value['exposures'];
            ++self::$countNestedExposures;
            $func();
            --self::$countNestedExposures;
            self::$rootExposures = &$parentRepresentation;
            self::$rootExposures[] = $value;
            return;
        }

        self::$rootExposures[] = $value;
    }

    public static function withOptions(array $options, \Closure $func): void
    {
        $oldDefaultOptions = self::$defaultOptions;
        self::$defaultOptions = $options + self::$defaultOptions;
        $func();
        self::$defaultOptions = $oldDefaultOptions;
    }

    public static function formatWith(string $name, \Closure $func): void
    {
        self::$formatters[$name] = $func;
    }

    public static function root(?string $plural, string $singular = \null): void
    {
        self::$collectionRoot = $plural;
        self::$root = $singular;
    }

    public static function presentCollection(bool $presentCollection = \false, string $collectionName = 'items'): void
    {
        self::$presentCollection = $presentCollection;
        self::$collectionName = $collectionName;
    }

    public static function represent($objects, array $options = [])
    {
        $entityClass = \get_called_class();
        if (\is_iterable($objects) && !Util::is_assoc($objects) && !empty($objects) && !self::$presentCollection) {
            $root = self::$collectionRoot;
            $inner = [];
            if (!isset($options['collection'])) $options['collection'] = \true;
            foreach ($objects as $obj)
                $inner[] = (new $entityClass($obj, $options))->presented();
        } else {
            $root = self::$root;
            if (!isset($options['collection'])) $options['collection'] = \false;
            if (self::$presentCollection)
                $objects = [self::$collectionName => $objects];
            $inner = (new $entityClass($objects, $options))->presented();
        }
        if (\array_key_exists('root', $options))
            $root = $options['root'];
        return $root
            ? [($root && isset($entityClass::$keyTransformer) ? ($entityClass::$keyTransformer)($root) : $root) => $inner]
            : $inner;
    }
}
