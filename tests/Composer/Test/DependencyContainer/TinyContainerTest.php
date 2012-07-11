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
 * Dependency container tests
 *
 * @author Alexey Prilipko <palex@farpost.com>
 */
class TinyContainerTest extends PHPUnit_Framework_TestCase
{
    public function testShouldCreateContainer()
    {
        $config = array();
        $parameters = array();
        $container = new TinyContainer($config, $parameters);
        $this->assertNotEmpty($container);
    }

    /**
     * @expectedException LogicException
     */
    public function testShouldThrowOnCircularDependencies()
    {
        $config = array(
            'a' => array(
                'value' => array('ref' => 'b'),
            ),
            'b' => array(
                'value' => array('ref' => 'c'),
            ),
            'c' => array(
                'value' => array('ref' => 'a'),
            ),
        );
        $parameters = array();
        $container = new TinyContainer($config, $parameters);
        $container->getInstance('a');
        $this->assertNotEmpty($container);
    }

    public function testResolveParameters()
    {
        $config = array(
            'a' => array(
                'value' => '{$param}',
            ),
        );
        $parameters = array();
        $container = new TinyContainer($config, $parameters);
        $container->setParameter('param', 'test');
        $a = $container->getInstance('a');
        $this->assertEquals('test', $a);
    }

    public function testShouldResolveDefaultImplementations()
    {
        $config = array(
            'dep' => array(
                'default-for' => 'Test\Composer\DependencyContainer\SampleDependency',
                'class' => 'Test\Composer\DependencyContainer\SampleDependency',
            ),
            'root' => array(
                'class' => 'Test\Composer\DependencyContainer\SampleRoot',
            ),
        );
        $parameters = array();
        $container = new TinyContainer($config, $parameters);
        $root = $container->getInstance('root');
        $this->assertNotEmpty($root);
    }

    public function testShouldResolveValues()
    {
        $config = array(
            'directValue' => array(
                'value' => 'direct'
            ),
            'paramValue' => array(
                'value' => array( 'param' => 'param' )
            ),
            'paramValue2' => array(
                'value' => '{$param}'
            ),
            'refValue' => array(
                'value' => array( 'ref' => 'directValue' )
            ),
            'intanceOfValue' => array(
                'value' => array( 'instance-of' => 'className' )
            ),
            'defaultForClassName' => array(
                'default-for' => 'className',
                'value' => 'className'
            ),
            'innerValue' => array(
                'value' => array( 'class' => 'Test\Composer\DependencyContainer\SampleDependency' )
            ),
            'arrayValue' => array(
                'value' => array(
                    'value1' => 'direct',
                    'value2' => '{$param}',
                    'value3' => array( 'ref' => 'directValue' ),
                    'value4' => array( 'instance-of' => 'className' ),
                    'value5' => array( 'class' => 'Test\Composer\DependencyContainer\SampleDependency' ),
                ),
            ),
        );
        $parameters = array();
        $container = new TinyContainer($config, $parameters);
        $container->setParameter('param', 'paramTest');
        $this->assertEquals('direct', $container->getInstance('directValue'));
        $this->assertEquals('paramTest', $container->getInstance('paramValue'));
        $this->assertEquals('paramTest', $container->getInstance('paramValue2'));
        $this->assertEquals('direct', $container->getInstance('refValue'));
        $this->assertEquals('className', $container->getInstance('intanceOfValue'));
        $this->assertInstanceOf('Test\Composer\DependencyContainer\SampleDependency', $container->getInstance('innerValue'));
        $this->assertEquals(array(
                'value1' => 'direct',
                'value2' => 'paramTest',
                'value3' => 'direct',
                'value4' => 'className',
                'value5' => new SampleDependency(),
            ),
            $container->getInstance('arrayValue'));
    }

    public function testShouldAllowCrossLinkingThroughSetters()
    {
        $config = array(
            'dep' => array(
                'class' => 'Test\Composer\DependencyContainer\SampleDependencyWithSetter',
                'properties' => array(
                    'root' => array('ref' => 'root')
                ),
            ),
            'root' => array(
                'class' => 'Test\Composer\DependencyContainer\SampleRoot',
                'args' => array(
                    'arg' => array('ref' => 'dep')
                ),
            ),
        );
        $parameters = array();
        $container = new TinyContainer($config, $parameters);
        $root = $container->getInstance('root');
        $this->assertEquals($root, $root->getDep()->getRoot());
        $dep = $container->getInstance('dep');
        $this->assertEquals($dep, $dep->getRoot()->getDep());
    }

    public function testShouldHonourLifetimeSetting()
    {
        $config = array(
            'singleton' => array(
                'class' => 'Test\Composer\DependencyContainer\SampleDependency',
            ),
            'instance' => array(
                'class' => 'Test\Composer\DependencyContainer\SampleDependency',
                'lifetime' => 'instance',
            ),
            'list' => array(
                'value' => array(
                    'item1' => array('ref' => 'singleton'),
                    'item2' => array('ref' => 'singleton'),
                    'item3' => array('ref' => 'instance'),
                    'item4' => array('ref' => 'instance'),
                ),
            ),
        );
        $parameters = array();
        $container = new TinyContainer($config, $parameters);
        $list = $container->getInstance('list');
        $this->assertTrue($list['item1'] === $list['item2']);
        $this->assertFalse($list['item3'] === $list['item4']);
    }

    public function testShouldCreateByFactory()
    {
        $config = array(
            'dep' => array(
                'factory' => array('class' => 'Test\Composer\DependencyContainer\SampleFactory'),
                'method' => 'createDep'
            ),
        );
        $parameters = array();
        $container = new TinyContainer($config, $parameters);
        $this->assertInstanceOf('Test\Composer\DependencyContainer\SampleDependency', $container->getInstance('dep'));
    }

    public function testShouldUseReflectionToGetUndefinedClasses()
    {
        $config = array(
            'root' => array(
                'class' => 'Test\Composer\DependencyContainer\SampleRoot'
            ),
        );
        $parameters = array();
        $container = new TinyContainer($config, $parameters);
        $root = $container->getInstance('root');
        $this->assertInstanceOf('Test\Composer\DependencyContainer\SampleRoot', $root);
        $this->assertInstanceOf('Test\Composer\DependencyContainer\SampleDependency', $root->getDep());
    }
}

class SampleDependency
{

}

class SampleDependencyWithSetter extends SampleDependency
{
    private $root;

    public function getRoot()
    {
        return $this->root;
    }

    public function setRoot(SampleRoot $root)
    {
        $this->root = $root;
    }
}

class SampleRoot
{
    private $dep;

    public function __construct(SampleDependency $arg)
    {
        $this->dep = $arg;
    }

    public function getDep()
    {
        return $this->dep;
    }
}

class SampleFactory
{
    public function createDep()
    {
        return new SampleDependency();
    }
}
