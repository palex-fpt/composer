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
        foreach ($config as $id => $entry) {
            if ('parameters' == $id) {
                $this->parameters = $entry;
            } else {
                $lifetime = $entry['lifetime'] ?: DependencyDefinition::LIFETIME_SINGLE;
                $class = $entry['class'];
                $defaultFor = $entry['default-for'] ?: null;
                $arguments = array();
                foreach ($entry['args'] ?: array() as $key => $argValue) {
                    if (isset($argValue[ArgumentDefinition::REF])) {
                        $arguments[$key] = new ArgumentDefinition(ArgumentDefinition::REF, $argValue[ArgumentDefinition::REF]);
                    } elseif (isset($argValue[ArgumentDefinition::PARAM])) {
                        $arguments[$key] = new ArgumentDefinition(ArgumentDefinition::PARAM, $argValue[ArgumentDefinition::PARAM]);
                    } elseif (isset($argValue[ArgumentDefinition::INSTANCE_OF])) {
                        $arguments[$key] = new ArgumentDefinition(ArgumentDefinition::INSTANCE_OF, $argValue[ArgumentDefinition::INSTANCE_OF]);
                    } elseif (isset($argValue[ArgumentDefinition::VALUE])) {
                        $arguments[$key] = new ArgumentDefinition(ArgumentDefinition::VALUE, $argValue[ArgumentDefinition::VALUE]);
                    } else {
                        // if value is array - try build it's arguments
                        $arguments[$key] = new ArgumentDefinition(ArgumentDefinition::VALUE, $argValue);
                    }
                }
                $this->config[$id] = new DependencyDefinition(
                    $id,
                    $class,
                    $defaultFor,
                    $lifetime,
                    $arguments
                );
                if ($defaultFor) {
                    $this->classDefaults[$defaultFor] = $this->config[$id];
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

    /**
     * @param DependencyDefinition $definition
     * @return mixed
     */
    private function createInstance(DependencyDefinition $definition)
    {
        $className = $definition->getClass();
        $ref = new \ReflectionClass($className);
        $argumentDefs = $definition->getArguments();
        $args = array();
        foreach ($ref->getConstructor()->getParameters() as $parameter) {
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

    private function getInstanceForClass($className) {
        if (isset($this->classDefaults[$className])) {
            return $this->getInstance($this->classDefaults[$className]);
        }

        throw new \InvalidArgumentException(sprintf('Requested instance of `%s`. There is no entry with default-for satisfying it.', $className));
    }

    private function getArgumentInstance(ArgumentDefinition $argDef)
    {
        switch ($argDef->getType()) {
            case ArgumentDefinition::REF:
                return $this->getInstance($argDef->getValue());

            case ArgumentDefinition::PARAM:
                return $this->parameters[$argDef->getValue()];

            case ArgumentDefinition::INSTANCE_OF:
                return $this->getInstanceForClass($argDef->getValue());

            case ArgumentDefinition::VALUE:
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

    public function __construct(
        $id,
        $class,
        $defaultFor,
        $lifetime,
        $arguments
    )
    {
        $this->id = $id;
        $this->class = $class;
        $this->defaultFor = $defaultFor;
        $this->lifetime = $lifetime;
        $this->arguments = $arguments;
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
}

class ArgumentDefinition {
    const VALUE = 'value';
    const REF = 'ref';
    const PARAM = 'param';
    const INSTANCE_OF = 'instance-of';

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
