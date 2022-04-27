<?php

namespace PhpGrape;

use PhpGrape\Exceptions\InvalidOptionException;
use PhpGrape\Exceptions\InvalidTypeException;
use PhpGrape\Exceptions\MissingPropertyException;
use PhpGrape\Reflection;
use PhpGrape\Util;


class Entity implements \JsonSerializable
{
    public $object;
    public array $options;
    private ?array $exposures = \null;

    public static array $globalFormatters = [];
    protected static $keyTransformer = null;
    private static array $adapters = [];

    public static function formatWith(string $name, \Closure $func): void
    {
        self::$globalFormatters[$name] = $func;
    }

    public static function transformKeys($keyTransformer): void
    {
        if (\is_string($keyTransformer) && \method_exists(Util::class, $keyTransformer)) {
            self::$keyTransformer = Util::class . '::' . $keyTransformer;
        } else {
            self::$keyTransformer = $keyTransformer;
        }
    }

    public function __construct($object, array $options = [])
    {
        $this->object = $object;
        $this->options = $options;
    }

    private static function filterProps($arr, bool $only): array
    {
        if (!\is_array($arr)) return [$arr => \true];
        $flat = [];
        if (Util::is_assoc($arr)) {
            if ($only)
                foreach ($arr as $key => $v) $flat[$key] = \true;
        } else {
            foreach ($arr as $attr) {
                if (\is_array($attr)) {
                    $flat = \array_merge($flat, self::filterProps($attr, $only));
                } else {
                    $flat[$attr] = \true;
                }
            }
        }
        return $flat;
    }

    private static function shouldIncludeKey(string $key, ?array $onlyArr, ?array $exceptArr): bool
    {
        return ($onlyArr === \null || isset($onlyArr[$key])) && !isset($exceptArr[$key]);
    }

    private static function changeEntityOptions(array $options, string $key): array
    {
        foreach (['only', 'except'] as $type) {
            if (!isset($options[$type])) continue;
            foreach ((array) $options[$type] as $attr) {
                if (isset($attr[$key]) && \is_array($attr)) {
                    $options[$type] = $attr[$key];
                    continue 2;
                }
            }
            unset($options[$type]);
        }
        return $options;
    }

    private function getKey(string $prop, array $exposeOptions): string
    {
        if (!isset($exposeOptions['as'])) return $prop;
        return $exposeOptions['as'] instanceof \Closure ? $exposeOptions['as']($this->object, $this->options) : $exposeOptions['as'];
    }

    private function checkCondition(array $exposeOptions): bool
    {
        if (!isset($exposeOptions['if'])) return \true;

        $if = $exposeOptions['if'];
        if ($if instanceof \Closure) return (bool) $if($this->object, $this->options);
        if (\is_string($if)) return (bool) (isset($this->options[$if]) && $this->options[$if]);
        if (\is_array($if)) {
            foreach ($if as $k => $v)
                if (!isset($this->options[$k]) || $this->options[$k] !== $v) return \false;
            return \true;
        }
        throw new InvalidOptionException('Invalid `if` option');
    }

    public static function setPropValueAdapter(string $name, ?array $adapter): void
    {
        if (!$adapter) {
            unset(self::$adapters[$name]);
        } else {
            $adapter['cache'] = new AdapterCache();
            self::$adapters[$name] = $adapter;
        }
    }

    protected function getArrayPropValue($prop, $safe)
    {
        if (isset($this->object[$prop]) || \array_key_exists($prop, $this->object))
            return $this->object[$prop] instanceof \Closure ? $this->object[$prop]() : $this->object[$prop];
        return $this->handleMissingProperty($prop, $safe);
    }

    protected function getObjectPropValue($prop, $safe)
    {
        // Object property. Public and not null
        if (isset($this->object->{$prop})) return $this->object->{$prop};

        // Object property. Null or not public
        if (\property_exists($this->object, $prop)) {
            $property = Reflection::getProperty(get_class($this->object), $prop);
            if ($property !== false) return $property->getValue($this->object);
        }

        // Object method.
        if (\method_exists($this->object, $prop)) {
            $method = Reflection::getMethod(get_class($this->object), $prop);
            if ($method !== false) return $method->invoke($this->object);
        }

        // Magical property
        if (\method_exists($this->object, '__get')) {
            try {
                return $this->object->__get($prop);
            } catch (\Exception $_) {
            }
        }

        // Magical method
        if (\method_exists($this->object, '__call')) {
            try {
                return $this->object->__call($prop, []);
            } catch (\Exception $_) {
            }
        }
        return $this->handleMissingProperty($prop, $safe);
    }

    protected function handleMissingProperty(string $prop, bool $safe)
    {
        if ($safe) return \null;

        $objStr = \is_array($this->object) ? \json_encode($this->object) : (\is_object($this->object) ? \get_class($this->object) : \strval($this->object));
        throw new MissingPropertyException('Missing property or method `' . $prop . '` on `' . $objStr . '`');
    }

    private function getValueFromExposure(array $expose, string $key)
    {
        $savedOptions = $this->options;
        $this->exposures = $expose['exposures'];
        $this->options = self::changeEntityOptions($savedOptions, $key);
        $value = $this->serializableArray();
        $this->options = $savedOptions;
        $this->exposures = \null;
        return $value;
    }

    private function getValue(array $expose, string $prop, string $key, array $exposeOptions, $entityClass)
    {
        // Value source
        if (isset($expose['func'])) {
            $value = $expose['func']($this->object, $this->options);
        } elseif (isset($expose['exposures'])) {
            $value = $this->getValueFromExposure($expose, $key);
        } else {
            $value = $this->getValueFromEntity($expose, $prop, $entityClass);
        }
        // Entity value
        if (isset($exposeOptions['using'])) {
            $options = self::changeEntityOptions($this->options, $key);
            unset($options['collection']);
            $options['root'] = \null;
            $value = $exposeOptions['using']::represent($value, $options);
        }
        // Serializable value
        if (\is_iterable($value)) {
            foreach ($value as $index => $obj)
                if (\is_object($obj) && \method_exists($obj, 'serializableArray') && \is_callable([$obj, 'serializableArray']))
                    $value[$index] = $obj->serializableArray();
        } elseif (\is_object($value) && \method_exists($value, 'serializableArray') && \is_callable([$value, 'serializableArray'])) {
            $value = $value->serializableArray();
        }
        // Default value
        if (isset($exposeOptions['default'])) {
            if (\null === $value || \false === $value) return $exposeOptions['default'];
            if (\is_string($value) && '' === \trim($value)) return $exposeOptions['default'];
            if (\is_array($value) && 0 === \count($value)) return $exposeOptions['default'];
        }
        return $value;
    }

    private function getValueFromEntity(array $expose, string $prop, $entityClass)
    {
        // Entity prop
        if (\property_exists($this, $prop)) {
            $property = Reflection::getProperty($entityClass, $prop);
            if ($property !== false) return $property->getValue($this);
        }
        // Entity method
        if (\method_exists($this, $prop)) {
            $method = Reflection::getMethod($entityClass, $prop);
            if ($method !== false) return $method->invoke($this);
        }

        // Run through adapters and then handle objects and arrays
        $safe = isset($expose['options']['safe']) && $expose['options']['safe'] === \true;
        foreach (self::$adapters as $adapter) {
            if ($adapter['condition']->call($this, $prop, $safe))
                return $adapter['getPropValue']->call($this, $prop, $safe, $adapter['cache']);
        }
        if (\is_object($this->object)) return $this->getObjectPropValue($prop, $safe);
        if (\is_array($this->object)) return $this->getArrayPropValue($prop, $safe);
        return $this->handleMissingProperty($prop, $safe);
    }

    public function presented()
    {
        return isset($this->options['serializable']) && $this->options['serializable'] ? $this->serializableArray() : $this;
    }

    private function setAttrPath(array $exposeOptions, array $attrPath, string $key)
    {
        if (isset($exposeOptions['attr_path'])) {
            $this->options['attr_path'] = $exposeOptions['attr_path'] instanceof \Closure
                ? $exposeOptions['attr_path']($this->object, $this->options)
                : $exposeOptions['attr_path'];
            if (!\is_array($this->options['attr_path'])) {
                $this->options['attr_path'] = $this->options['attr_path'] ? [$this->options['attr_path']] : [];
            }
        } else {
            $attrPath[] = $key;
            $this->options['attr_path'] = $attrPath;
        }
    }

    public function serializableArray(): ?array
    {
        if ($this->object === \null) return \null;

        // Ensure entity's been loaded
        $entityClass = \get_called_class();
        $entityClass::load();

        // Options
        $onlyArr = isset($this->options['only']) ? self::filterProps($this->options['only'], \true) : \null;
        $exceptArr = isset($this->options['except']) ? self::filterProps($this->options['except'], \false) : \null;
        $attrPath = isset($this->options['attr_path']) ? $this->options['attr_path'] : [];

        $root = [];
        $inRoot = \false;
        $exposures = [];
        $rootExposures = isset($this->exposures) ? $this->exposures : $entityClass::$rootExposures;
        $hasKeyTransformer = isset(self::$keyTransformer);
        foreach ($rootExposures as $expose) {
            $exposeOptions = $expose['options'];

            if (!$this->checkCondition($exposeOptions)) continue;

            $prop = $expose['prop'];
            $key = $this->getKey($prop, $exposeOptions);
            if ($hasKeyTransformer) $key = (self::$keyTransformer)($key);

            $this->setAttrPath($exposeOptions, $attrPath, $key);

            $value = $this->getValue($expose, $prop, $key, $exposeOptions, $entityClass);

            if ($value !== \null || !isset($exposeOptions['expose_null']) || $exposeOptions['expose_null'] !== \false) {
                // Nested
                if (isset($expose['exposures']) && \array_key_exists($key, $exposures)) {
                    $isAssocRep = \is_array($exposures[$key]) && Util::is_assoc($exposures[$key]);
                    $isAssocVal = \is_array($value) && Util::is_assoc($value);
                    if ($isAssocRep && !$isAssocVal) { // add $value to the beginning of assoc array ($exposures[$key])
                        \array_unshift($value, $exposures[$key]);
                    } elseif (!$isAssocRep && $isAssocVal) { // add assoc array ($value) to indexed array ($exposures[$key])
                        $exposures[$key][] = $value;
                        $value = $exposures[$key];
                    } else { // merge assoc arrays
                        $value += $exposures[$key];
                    }
                }

                // Format value
                if (isset($exposeOptions['format_with'])) {
                    $formatWith = $exposeOptions['format_with'];
                    if ($formatWith instanceof \Closure) {
                        $value = $formatWith->call($this, $value);
                    } elseif (isset($entityClass::$formatters[$formatWith])) {
                        $value = $entityClass::$formatters[$formatWith]->call($this, $value);
                    } elseif (isset(self::$globalFormatters[$formatWith])) {
                        $value = self::$globalFormatters[$formatWith]->call($this, $value);
                    } else {
                        throw new InvalidOptionException('Invalid format_with option' . (\is_string($formatWith) ? (': ' . $formatWith) : ''));
                    }
                }

                // Merge
                if (isset($exposeOptions['merge']) && $exposeOptions['merge']) {
                    if (!\is_array($value) && ($value === \null || \is_object($value))) {
                        try {
                            $value = (array) $value;
                        } catch (\ErrorException $e) {
                        }
                    }
                    if (\is_array($value)) {
                        if (!Util::is_assoc($value) && !empty($value)) {
                            $root = \array_merge($root, $value);
                        } else {
                            $func = $exposeOptions['merge'] instanceof \Closure ? $exposeOptions['merge'] : \null;
                            foreach ($value as $k => $v) {
                                if (self::shouldIncludeKey($k, $onlyArr, $exceptArr)) {
                                    $exposures[$k] = isset($exposures[$k]) && $func !== \null ? $func($k, $exposures[$k], $v) : $v;
                                }
                            }
                            if (!$inRoot) {
                                $inRoot = \true;
                                $root[] = &$exposures;
                            }
                        }
                        continue;
                    }

                    throw new InvalidTypeException('Merge error: `' . \join('.', $this->options['attr_path']) . '` should be an array');
                }

                if (self::shouldIncludeKey($key, $onlyArr, $exceptArr)) {
                    $exposures[$key] = $value;
                    if (!$inRoot) {
                        $inRoot = \true;
                        $root[] = &$exposures;
                    }
                }
            }
        }

        return $root && ($inRoot === \false || \count($root) > 1) ? $root : $exposures;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->serializableArray();
    }

    public function toJson()
    {
        return \json_encode($this->serializableArray());
    }

    public function toXml(array $options = [])
    {
        return Util::arrayToXML($this->serializableArray(), $options);
    }
}

// Eloquent adapter. Lots of magic here...
// Trying to disentangle this mess to know if a prop is real (and potentially null) or not ...
Entity::setPropValueAdapter('Eloquent', [
    'condition' => function () {
        $model = 'Illuminate\Database\Eloquent\Model';
        return $this->object instanceof $model;
    },
    'getPropValue' => function ($prop, $safe, $cache) {
        // Model attributes or mutated attributes
        if (\array_key_exists($prop, $this->object->getAttributes()) || \in_array($prop, $this->object->getMutatedAttributes(), \true))
            return $this->object->getAttributeValue($prop);

        $class = get_class($this->object);
        // Cached relations
        if ($cache->get($class, $prop) !== null) return $this->object->getAttribute($prop);

        // Object method or Relation
        if (\method_exists($this->object, $prop)) {
            $method = Reflection::getMethod($class, $prop);
            if ($method !== \false) {
                $value = $method->invoke($this->object);

                // Relation
                $relation = 'Illuminate\Database\Eloquent\Relations\Relation';
                if ($value instanceof $relation) {
                    $cache->set($class, $prop, \true);
                    $value = $value->getResults();
                    $this->object->setRelation($prop, $value);
                }
                return $value;
            }
        }

        // Could also cover relations or attributes, way more slower though
        if (isset($this->object->{$prop})) return $this->object->{$prop};

        // User defined property or a private/protected property
        if (\property_exists($this->object, $prop)) {
            $property = Reflection::getProperty($class, $prop);
            if ($property !== \false) return $property->getValue($this->object);
        }

        return $this->handleMissingProperty($prop, $safe);
    }
]);
