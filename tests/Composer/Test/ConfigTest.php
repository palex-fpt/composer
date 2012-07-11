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

namespace Composer\Test;

use Composer\Config;

class ConfigTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider dataAddPackagistRepository
     */
    public function testAddPackagistRepository($expected, $localConfig, $systemConfig = null)
    {
        $defaultConfig = array("packagist"=> array(
            "type"=> "composer",
            "url"=> "http://packagist.org"
            ));

        if ($systemConfig) {
            $config = new Config(
                array('config' => array('default-repositories' => $defaultConfig)),
                array('config' => array('default-repositories' => $systemConfig)),
                array('repositories' => $localConfig)
            );
        } else {
            $config = new Config(
                array('config' => array('default-repositories' => $defaultConfig)),
                array('repositories' => $localConfig)
            );
        }

        $this->assertEquals($expected, $config->getObject('repositories'));
    }

    public function dataAddPackagistRepository()
    {
        $data = array();
        $data['local config inherits system defaults'] = array(
            array(
                'packagist' => array('type' => 'composer', 'url' => 'http://packagist.org')
            ),
            array(),
        );

        $data['local config can disable system config by name'] = array(
            array(),
            array(
                array('packagist' => false),
            )
        );

        $data['local config adds above defaults'] = array(
            array(
                0 => array('type' => 'vcs', 'url' => 'git://github.com/composer/composer.git'),
                1 => array('type' => 'pear', 'url' => 'http://pear.composer.org'),
                'packagist' => array('type' => 'composer', 'url' => 'http://packagist.org'),
            ),
            array(
                array('type' => 'vcs', 'url' => 'git://github.com/composer/composer.git'),
                array('type' => 'pear', 'url' => 'http://pear.composer.org'),
            ),
        );

        $data['system config adds above core defaults'] = array(
            array(
                'example.com' => array('type' => 'composer', 'url' => 'http://example.com'),
                'packagist' => array('type' => 'composer', 'url' => 'http://packagist.org')
            ),
            array(),
            array(
                'example.com' => array('type' => 'composer', 'url' => 'http://example.com'),
            ),
        );

        $data['local config can disable repos by name and re-add them anonymously to bring them above system config'] = array(
            array(
                1 => array('type' => 'composer', 'url' => 'http://packagist.org'),
                'example.com' => array('type' => 'composer', 'url' => 'http://example.com')
            ),
            array(
                array('packagist' => false),
                array('type' => 'composer', 'url' => 'http://packagist.org')
            ),
            array(
                'example.com' => array('type' => 'composer', 'url' => 'http://example.com'),
            ),
        );

        $data['local config can override by name to bring a repo above system config'] = array(
            array(
                'packagist' => array('type' => 'composer', 'url' => 'http://packagistnew.org'),
                'example.com' => array('type' => 'composer', 'url' => 'http://example.com')
            ),
            array(
                'packagist' => array('type' => 'composer', 'url' => 'http://packagistnew.org')
            ),
            array(
                'example.com' => array('type' => 'composer', 'url' => 'http://example.com'),
            ),
        );

        return $data;
    }
}
