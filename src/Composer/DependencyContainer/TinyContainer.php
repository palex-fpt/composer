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
class TinyContainer
{
    /** @var DependencyDefinition[]  */
    private $config; // [id-name => DependencyDefinition]
    /** @var array list of parameters */
    private $parameters = array();
    /** @var DependencyDefinition list of class defaults */
    private $classDefaults = array(); // [class => DependencyDefinition]
    /** @var array list */
    private $refs = array(); // [id-name => instance]

    /**
     * Create instance of container.
     *
     * @param array $dependencyConfig deep array of dependency configuration
     * @param array $parameters       plain array of parameters. It can be expanded with setParameter method.
     */
    public function __construct($dependencyConfig, $parameters = array())
    {
        foreach ($parameters as $key => $value) {
            $this->parameters[$key] = $value;
        }

        foreach ($dependencyConfig as $id => $entry) {
            $lifetime = $this->getValue($entry, 'lifetime', DependencyDefinition::LIFETIME_SINGLE);
            $class = $this->getValue($entry, 'class', null);
            $defaultFor = $this->getValue($entry, 'default-for', null);
            $arguments = array();
            foreach ($this->getValue($entry, 'args', array()) as $key => $argValue) {
                $arguments[$key] = $this->buildValue($argValue);
            }
            $properties = array();
            foreach ($this->getValue($entry, 'properties', array()) as $key => $argValue) {
                $properties[$key] = $this->buildValue($argValue);
            }
            $value = $this->getValue($entry, 'value', null);
            if (!empty($value)) {
                $value = $this->buildValue($value);
            }
            $factory = $this->getValue($entry, 'factory', null);
            $factoryMethod = $this->getValue($entry, 'method', null);
            if (!empty($factory)) {
                $factory = $this->buildValue($factory);
            }
            try {
                $this->config[$id] = new DependencyDefinition(
                    $id,
                    $class,
                    $defaultFor,
                    $lifetime,
                    $arguments,
                    $properties,
                    $value,
                    $factory,
                    $factoryMethod
                );
                if ($defaultFor) {
                    $this->classDefaults[$defaultFor] = $this->config[$id];
                }
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException(sprintf("Failed to build container from configuration. Failed entry `%s`.", var_export($entry, true)), 0, $e);
            }
        }
    }

    /**
     * Get instance from container.
     *
     * @param  string          $name instance id
     * @throws \LogicException when instance can not be built
     * @return mixed           instance value
     */
    public function getInstance($name)
    {
        $context = new CreationContext();
        try {
            $instance = $this->getInternalInstance($name, $context);
            while ($delayed = $context->popDelayed()) {
                $this->populateInstance($delayed['instance'], $delayed['definition'], $context);
            }

            return $instance;
        } catch (\LogicException $e) {
            throw new \LogicException(sprintf("Failed to get container instance `%s`.\n Trace:\n%s", (string) $name, (string) $context), 0, $e);
        }
    }

    /**
     * Set parameter value
     *
     * @param  string $name
     * @param  mixed  $value
     * @return self
     */
    public function setParameter($name, $value)
    {
        $this->parameters[$name] = $value;

        return $this;
    }

    /**
     * Get instance of object.
     *
     * @param  string                    $name
     * @param  CreationContext           $context
     * @return mixed
     * @throws \InvalidArgumentException
     */
    private function getInternalInstance($name, CreationContext $context)
    {
        $context->push('get', $name);
        if (!isset($this->config[$name])) {
            throw new \InvalidArgumentException(sprintf("There is no definition for `%s`.", $name));
        }

        $definition = $this->config[$name];
        if ($definition->getLifetime() == DependencyDefinition::LIFETIME_INSTANCE) {
            $instance = $this->createInstance($definition, $context);
        } elseif (!isset($this->refs[$definition->getId()])) {
            $instance = $this->createInstance($definition, $context);
            $this->refs[$definition->getId()] = $instance;
        } else {
            $instance = $this->refs[$definition->getId()];
        }
        $context->pop('get', $name);

        return $instance;
    }

    /**
     * Hash getter with default
     *
     * @param  array  $collection
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    private function getValue($collection, $key, $default)
    {
        if (isset($collection[$key])) {
            return $collection[$key];
        }

        return $default;
    }

    private $innerId = 1;

    /**
     * Build valueDefinition for dependency configuration entry
     *
     * @param  array              $argValue
     * @return ArgumentDefinition
     */
    private function buildValue($argValue)
    {
        if (!is_array($argValue)) {
            return new ArgumentDefinition(ArgumentDefinition::VALUE, $argValue);
        } elseif (isset($argValue['class']) || isset($argValue['factory']) || isset($argValue['value'])) {
            $className = $argValue['class'];
            $arguments = array();
            foreach ($this->getValue($argValue, 'args', array()) as $key => $value) {
                $arguments[$key] = $this->buildValue($value);
            }
            $properties = array();
            foreach ($this->getValue($argValue, 'properties', array()) as $key => $value) {
                $properties[$key] = $this->buildValue($value);
            }
            $value = $this->getValue($argValue, 'value', null);
            if (!empty($value)) {
                $value = $this->buildValue($value);
            }
            $factory = $this->getValue($argValue, 'factory', null);
            $factoryMethod = $this->getValue($argValue, 'method', null);
            if (!empty($factory)) {
                $factory = $this->buildValue($factory);
            }
            $defId = '#innerDef_'.$this->innerId++;
            $this->config[$defId] = new DependencyDefinition(
                $defId,
                $className,
                null,
                DependencyDefinition::LIFETIME_INSTANCE,
                $arguments,
                $properties,
                $value,
                $factory,
                $factoryMethod
            );

            return new ArgumentDefinition(ArgumentDefinition::REF, $defId);
        } elseif (isset($argValue[ArgumentDefinition::REF])) {
            return new ArgumentDefinition(ArgumentDefinition::REF, $argValue[ArgumentDefinition::REF]);
        } elseif (isset($argValue[ArgumentDefinition::PARAM])) {
            return new ArgumentDefinition(ArgumentDefinition::PARAM, $argValue[ArgumentDefinition::PARAM]);
        } elseif (isset($argValue[ArgumentDefinition::INSTANCE_OF])) {
            return new ArgumentDefinition(ArgumentDefinition::INSTANCE_OF, $argValue[ArgumentDefinition::INSTANCE_OF]);
        } elseif (isset($argValue[ArgumentDefinition::VALUE])) {
            return new ArgumentDefinition(ArgumentDefinition::VALUE, $this->buildValue($argValue[ArgumentDefinition::VALUE]));
        } else {
            $arrayArgs = array();
            foreach ($argValue as $key => $value) {
                $arrayArgs[$key] = $this->buildValue($value);
            }

            return new ArgumentDefinition(ArgumentDefinition::VALUE, $arrayArgs);
        }
    }

    /**
     * Populate instance properties.
     * It is called after when object graph is ready to return to client
     *
     * @param  mixed                $instance
     * @param  DependencyDefinition $definition
     * @param  CreationContext      $context
     * @throws \LogicException
     */
    private function populateInstance($instance, DependencyDefinition $definition, CreationContext $context)
    {
        $className = $definition->getClass();
        if (!class_exists($className)) {
            throw new \LogicException(sprintf("There is no class with name `%s`.", $className));
        }
        $ref = new \ReflectionClass($className);
        $propertyDefs = $definition->getProperties();
        foreach ($propertyDefs as $name => $propertyDef) {
            $method = $ref->getMethod('set'.ucfirst($name));
            if ($method) {
                $method->invoke($instance, $this->getArgumentInstance($propertyDef, $context));
            } else {
                throw new \LogicException(sprintf("There is no argument supplied for parameter `%s`.", $name));
            }
        }
    }

    /**
     * Create object instance
     *
     * @param  DependencyDefinition $definition
     * @return mixed
     */
    private function createInstance(DependencyDefinition $definition, CreationContext $context)
    {
        $context->push('create', (string) $definition->getId());

        if ($definition->getClass()) {
            $className = $definition->getClass();
            if (!class_exists($className)) {
                throw new \LogicException(sprintf("There is no class with name `%s`.", $className));
            }
            $ref = new \ReflectionClass($className);
            $propertyDefs = $definition->getProperties();
            $constructor = $ref->getConstructor();

            if ($constructor) {
                $args = $this->buildMethodArgs($constructor, $definition->getArguments(), $context);
                $instance = $ref->newInstanceArgs($args);
                $this->refs[$definition->getId()] = $instance;
            } else {
                $instance = $ref->newInstance();
            }

            if (count($propertyDefs)) {
                // delay loading
                $context->delay($instance, $definition);
            }
        } elseif ($definition->getFactory()) {
            // factory method
            $factory = $this->getArgumentInstance($definition->getFactory(), $context);
            $methodName = $definition->getMethod();
            $method = new \ReflectionMethod($factory, $methodName);
            $args = $this->buildMethodArgs($method, $definition->getArguments(), $context);
            $instance = $method->invokeArgs($factory, $args);
        } else {
            $instance = $this->getArgumentInstance($definition->getValue(), $context);
        }

        $context->pop('create', (string) $definition->getId());

        return $instance;
    }

    /**
     * Build method arguments.
     *
     * @param  \ReflectionMethod    $method
     * @param  ArgumentDefinition[] $args
     * @return array                list of initialized arguments
     */
    private function buildMethodArgs(\ReflectionMethod $method, $argumentDefinitions, CreationContext $context)
    {
        $args = array();
        foreach ($method->getParameters() as $parameter) {
            if (isset($argumentDefinitions[$parameter->getName()])) {
                $argumentDef = $argumentDefinitions[$parameter->getName()];
                $args[] = $this->getArgumentInstance($argumentDef, $context);
            } elseif ($parameter->getClass()) {
                $args[] = $this->getInstanceForClass($parameter->getClass()->getName(), $context);
            } elseif ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
            } elseif ($parameter->isOptional()) {
                $args[] = null;
            } else {
                throw new \InvalidArgumentException(sprintf("There is no argument supplied for parameter `%s`.", $parameter->getName()));
            }
        }

        return $args;
    }

    /**
     * Create instance for requested class
     *
     * @param  string                    $className
     * @param  CreationContext           $context
     * @return mixed
     * @throws \InvalidArgumentException
     */
    private function getInstanceForClass($className, CreationContext $context)
    {
        if (isset($this->classDefaults[$className])) {
            return $this->getInternalInstance($this->classDefaults[$className]->getId(), $context);
        }
        if (class_exists($className)) {
            $id = '#class_'.$className;
            $def = new DependencyDefinition($id, $className, null, DependencyDefinition::LIFETIME_INSTANCE, array(), array(), null, null, null);
            $this->config[$id] = $def;
            return $this->getInternalInstance($id, $context);
        }

        throw new \InvalidArgumentException(sprintf("Requested instance of `%s`. There is no entry with default-for satisfying it.", $className));
    }

    /**
     * Create instance for one of method arguments
     *
     * @param  ArgumentDefinition        $argDef
     * @param  CreationContext           $context
     * @return mixed
     * @throws \InvalidArgumentException
     */
    private function getArgumentInstance(ArgumentDefinition $argDef, CreationContext $context)
    {
        switch ($argDef->getType()) {
            case ArgumentDefinition::REF:
                return $this->getInternalInstance($argDef->getValue(), $context);

            case ArgumentDefinition::PARAM:
                if (!isset($this->parameters[$argDef->getValue()])) {
                    throw new \InvalidArgumentException(sprintf("Requested undefined parameter `%s`.", $argDef->getValue()));
                }

                return $this->parameters[$argDef->getValue()];

            case ArgumentDefinition::INSTANCE_OF:
                return $this->getInstanceForClass($argDef->getValue(), $context);

            case ArgumentDefinition::VALUE:
                if (is_string($argDef->getValue())) {
                    $parameters = $this->parameters;

                    return preg_replace_callback('#\{\$(.+)\}#', function ($match) use ($parameters) {
                        return isset($parameters[$match[1]]) ? $parameters[$match[1]] : $match[1];
                    }, $argDef->getValue());
                } elseif (is_array($argDef->getValue())) {
                    $values = array();
                    foreach ($argDef->getValue() as $key => $value) {
                        $values[$key] = $this->getArgumentInstance($value, $context);
                    }

                    return $values;
                }

                return $argDef->getValue();

            default:
                throw new \InvalidArgumentException(sprintf("Undefined argument type `%s`.", $argDef->getType()));
        }
    }
}
