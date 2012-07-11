<?php

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Composer\DependencyContainer;

/**
 * Dependency container
 *
 * @author Alexey Prilipko <palex@farpost.com>
 */
class DependencyDefinition
{
    const LIFETIME_INSTANCE = 'instance';
    const LIFETIME_SINGLE = 'single';

    private $id;
    private $class;
    private $defaultFor;
    private $lifetime;
    private $arguments;
    private $properties;
    private $value;
    private $factory;
    private $method;

    /**
     * @param $id
     * @param $class
     * @param $defaultFor
     * @param $lifetime
     * @param ArgumentDefinition[] $arguments
     * @param ArgumentDefinition[] $properties
     * @param $value
     */
    public function __construct(
        $id,
        $class,
        $defaultFor,
        $lifetime,
        $arguments,
        $properties,
        $value,
        $factory,
        $method
    )
    {
        if (empty($class) && empty($value) && empty($factory)) {
            throw new \InvalidArgumentException('Dependency should have at least one of class or value or factory property set.');
        }

        if (
            !empty($class) && (!empty($value) || !empty($factory))
            || !empty($value) && (!empty($class) || !empty($factory))
            || !empty($factory) && (!empty($value) || !empty($class))
        ) {
            throw new \InvalidArgumentException('Dependency should have only one of class, value or factory property set.');
        }

        if (!empty($factory) && empty($method)) {
            throw new \InvalidArgumentException('Factory dependency should have method property set.');
        }

        $this->id = $id;
        $this->class = $class;
        $this->defaultFor = $defaultFor;
        $this->lifetime = $lifetime;
        $this->arguments = $arguments;
        $this->properties = $properties;
        $this->value = $value;
        $this->factory = $factory;
        $this->method = $method;
    }

    public function getArguments()
    {
        return $this->arguments;
    }

    public function getClass()
    {
        return $this->class;
    }

    public function getDefaultFor()
    {
        return $this->defaultFor;
    }

    public function getLifetime()
    {
        return $this->lifetime;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getProperties()
    {
        return $this->properties;
    }

    public function getFactory()
    {
        return $this->factory;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function __toString()
    {
        if ($this->getClass()) {
            return (string) $this->getClass();
        }

        if ($this->getFactory()) {
            return (string) $this->getFactory();
        }

        if ($this->getValue()) {
            return (string) $this->getValue();
        }

        return '--invalid--';
    }
}
