<?php

namespace PhpGrape;


class AdapterCache
{
    private array $storage = [];

    public function get($class, $prop)
    {
        return isset($this->storage[$class][$prop]) ? $this->storage[$class][$prop] : null;
    }

    public function set($class, $prop, $value)
    {
        if (!isset($this->storage[$class])) $this->storage[$class] = [];
        $this->storage[$class][$prop] = $value;
    }
}
