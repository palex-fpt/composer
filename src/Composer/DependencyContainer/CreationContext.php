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
class CreationContext
{
    private $context;
    private $delayed;
    private $trace;

    public function __construct()
    {
        $this->delayed = array();
        $this->context = array();
        $this->trace = array();
    }

    public function push($operationType, $id)
    {
        foreach ($this->context as $operation) {
            if ($operation['id'] == $id && $operation['type'] == $operationType) {
                throw new \LogicException(sprintf("Cyclic dependency detected.\n Operation: %s.", var_export($operation, true)));
            }
        }
        array_unshift($this->context, array('type' => $operationType, 'id' => $id));
        $this->trace[] = array('depth' => count($this->context), 'type' => '+ '.$operationType, 'id' => $id);
    }

    public function pop($operationType, $id)
    {
        $op = array_shift($this->context);
        if ($op['id'] != $id || $op['type'] != $operationType) {
            throw new \LogicException("Mismatching operations in context.");
        }

        $this->trace[] = array('depth' => count($this->context)+1, 'type' => '- '.$operationType, 'id' => $id);
    }

    public function delay($instance, DependencyDefinition $definition)
    {
        array_unshift($this->delayed, array(
            'instance' => $instance,
            'definition' => $definition,
        ));
    }

    public function popDelayed()
    {
        return array_shift($this->delayed);
    }

    public function __toString()
    {
        return implode("\n", array_map(function($item) { return str_repeat('  ', $item['depth']) . "{$item['type']} {$item['id']}"; }, $this->trace));
    }
}
