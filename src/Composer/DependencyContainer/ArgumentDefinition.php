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
 * Argument definition for dependency container
 *
 * @author Alexey Prilipko <palex@farpost.com>
 */
class ArgumentDefinition
{
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

    public function __toString()
    {
        return $this->type . ': ' . (string) $this->value;
    }
}
