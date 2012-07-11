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

namespace Test\Composer\DependencyContainer;

use PHPUnit_Framework_TestCase;
use Composer\DependencyContainer\TinyContainer;

/**
 * Dependency container
 *
 * @author Alexey Prilipko <palex@farpost.com>
 */
class DependencyDefinitionTest extends PHPUnit_Framework_TestCase
{
    public function testShouldCreateContainer()
    {
        $config = array();
        $parameters = array();
        $container = new TinyContainer($config, $parameters);
        $this->assertNotEmpty($container);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testShouldNotAllowClassAndFactoryInOneEntry()
    {
        $config = array(
            'entry' => array(
                'class' => 'class',
                'factory' => 'factory',
            )
        );
        $parameters = array();
        $container = new TinyContainer($config, $parameters);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testShouldNotAllowClassAndValueInOneEntry()
    {
        $config = array(
            'entry' => array(
                'class' => 'class',
                'value' => 'value',
            )
        );
        $parameters = array();
        $container = new TinyContainer($config, $parameters);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testShouldNotAllowFactoryAndValueInOneEntry()
    {
        $config = array(
            'entry' => array(
                'value' => 'value',
                'factory' => 'factory',
            )
        );
        $parameters = array();
        $container = new TinyContainer($config, $parameters);
    }
}
