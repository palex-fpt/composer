<?php

namespace Composer;

class DependencyContainer
{
    /** @var DependencyDefinition[]  */
    private $config; // [id-name => DependencyDefinition]
    /** @var array list of parameters */
    private $parameters = array();
    /** @var DependencyDefinition list of class defaults */
    private $classDefaults = array(); // [class => DependencyDefinition]
    /** @var array list */
    private $refs = array(); // [id-name => instance]

    public function __construct($config)
    {
        // build configuration
        foreach ($config as $key => $value) {
            if ('objects' != $key) {
                $this->parameters[$key] = $value;
            } else {
                foreach ($value as $id => $entry) {
                    $lifetime = $this->getValue($entry, 'lifetime', DependencyDefinition::LIFETIME_SINGLE);
                    $class = $this->getValue($entry, 'class', null);
                    $defaultFor = $this->getValue($entry, 'default-for', null);
                    $arguments = array();
                    foreach ($this->getValue($entry, 'args', array()) as $key => $argValue) {
                        $arguments[$key] = $this->buildValue($argValue);
                    }
                    $value = $this->getValue($entry, 'value', null);
                    if (!empty($value)) {
                        $value = $this->buildValue($value);
                    }
                    $this->config[$id] = new DependencyDefinition(
                        $id,
                        $class,
                        $defaultFor,
                        $lifetime,
                        $arguments,
                        $value
                    );
                    if ($defaultFor) {
                        $this->classDefaults[$defaultFor] = $this->config[$id];
                    }
                }
            }
        }
    }

    /**
     * get/create instance
     * @param string $name instance id
     */
    public function getInstance($name) {
        // find decl;
        if (!isset($this->config[$name])) {
            throw new \InvalidArgumentException(sprintf('There is no definition for `%s`', $name));
        }

        $definition = $this->config[$name];
        if ($definition->getLifetime() == DependencyDefinition::LIFETIME_INSTANCE) {
            return $this->createInstance($definition);
        }

        if (!isset($this->refs[$definition->getId()])) {
            $this->refs[$definition->getId()] = $this->createInstance($definition);
        }
        return $this->refs[$definition->getId()];
    }

    public function setParameter($name, $value)
    {
        $this->parameters[$name] = $value;
    }

    private function getValue($collection, $key, $default)
    {
        if (isset($collection[$key])) {
            return $collection[$key];
        }
        return $default;
    }

    private $innerId = 1;
    private function buildValue($argValue)
    {
        if (!is_array($argValue)) {
            return new ArgumentDefinition(ArgumentDefinition::VALUE, $argValue);
        } elseif (isset($argValue[ArgumentDefinition::INNER_DEF])) {
            $className = $argValue['class'];
            $args = $this->getValue($argValue, 'args', array());
            $arrayArgs = array();
            foreach ($args as $key => $value) {
                $arrayArgs[$key] = $this->buildValue($value);
            }
            $defId = 'innerDef_'.$this->innerId++;
            $this->config[$defId] = new DependencyDefinition(
                $defId,
                $className,
                null,
                DependencyDefinition::LIFETIME_INSTANCE,
                $arrayArgs,
                null
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
     * @param DependencyDefinition $definition
     * @return mixed
     */
    private function createInstance(DependencyDefinition $definition)
    {
        var_dump($definition->getClass());
        $className = $definition->getClass();
        if (empty($className)) {
            return $this->getArgumentInstance($definition->getValue());
        }
        $ref = new \ReflectionClass($className);
        $argumentDefs = $definition->getArguments();
        $args = array();
        $constructor = $ref->getConstructor();

        if ($constructor) {
            foreach ($constructor->getParameters() as $parameter) {
                if (isset($argumentDefs[$parameter->getName()])) {
                    $argumentDef = $argumentDefs[$parameter->getName()];
                    $args[] = $this->getArgumentInstance($argumentDef);
                } elseif ($parameter->getClass()) {
                    $args[] = $this->getInstanceForClass($parameter->getClass()->getName());
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $args[] = $parameter->getDefaultValue();
                } elseif ($parameter->isOptional()) {
                    $args[] = null;
                } else {
                    throw new \InvalidArgumentException($parameter->getName());
                }
            }
            return $ref->newInstanceArgs($args);
        }

        return $ref->newInstance();
    }

    private function getInstanceForClass($className) {
        if (isset($this->classDefaults[$className])) {
            return $this->getInstance($this->classDefaults[$className]->getId());
        }

        throw new \InvalidArgumentException(sprintf('Requested instance of `%s`. There is no entry with default-for satisfying it.', $className));
    }

    private function getArgumentInstance(ArgumentDefinition $argDef)
    {
        switch ($argDef->getType()) {
            case ArgumentDefinition::REF:
                return $this->getInstance($argDef->getValue());

            case ArgumentDefinition::PARAM:
                if (!isset($this->parameters[$argDef->getValue()])) {
                    throw new \InvalidArgumentException(sprintf('Requested unknown parameter: `%s`', $argDef->getValue()));
                }
                return $this->parameters[$argDef->getValue()];

            case ArgumentDefinition::INSTANCE_OF:
                return $this->getInstanceForClass($argDef->getValue());

            case ArgumentDefinition::VALUE:
                if (is_string($argDef->getValue())) {
                    $parameters = $this->parameters;
                    return preg_replace_callback('#\{\$(.+)\}#', function ($match) use ($parameters) {
                        return isset($parameters[$match[1]]) ? $parameters[$match[1]] : $match[1];
                    }, $argDef->getValue());
                } elseif (is_array($argDef->getValue())) {
                    $values = array();
                    foreach ($argDef->getValue() as $key => $value) {
                        $values[$key] = $this->getArgumentInstance($value);
                    }
                    return $values;
                }
                return $argDef->getValue();

            default:
                throw new \InvalidArgumentException(sprintf('Undefined argument type: `%s`', $argDef->getType()));
        }
    }
}

class DependencyDefinition
{
    const LIFETIME_INSTANCE = 'instance';
    const LIFETIME_SINGLE = 'single';

    private $id;
    private $class;
    private $defaultFor;
    private $lifetime;
    private $arguments;
    private $value;

    public function __construct(
        $id,
        $class,
        $defaultFor,
        $lifetime,
        $arguments,
        $value
    )
    {
        $this->id = $id;
        $this->class = $class;
        $this->defaultFor = $defaultFor;
        $this->lifetime = $lifetime;
        $this->arguments = $arguments;
        $this->value = $value;
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
}

class ArgumentDefinition {
    const VALUE = 'value';
    const REF = 'ref';
    const PARAM = 'param';
    const INSTANCE_OF = 'instance-of';
    const INNER_DEF = 'class';

    private $type;
    private $value;

    public function __construct(
        $type,
        $value
    )
    {
        $this->type = $type;
        $this->value = $value;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getValue()
    {
        return $this->value;
    }
}
