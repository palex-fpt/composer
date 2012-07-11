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

use Composer\Json\JsonFile;
use Composer\ConfigFactory;

class ConfigFactoryTest extends TestCase
{
    public function testCreateConfig()
    {
        $composerFile = new JsonFile('composer.json');
        $configFactory = new ConfigFactory();
        $config = $configFactory->createConfig($composerFile);

        $this->assertNotNull($config);
    }

    public function testShouldMergeSettings()
    {
        $config = new \Composer\Config(
            array(
                'key1' => 'value1',
                'key2' => 'value2',
                'key3' => 'value3',
            ),
            array(
                'insert2' => 'insert2',
                'key2' => 'override2',
            ),
            array(
                'insert3' => 'insert3',
                'key3' => array(
                    'deep_key3' => 'deep3',
                    'deep_key4' => 'deep4',
                    ),
            ),
            array(
                'key3' => array(
                    'deep_key4' => 'override4',
                    'deep_insert4' => 'insert4',
                ),
            )
        );

        $this->assertSame(
            array(
                'config' => array(
                    'repositories' => array(),
                ),
                'key1' => 'value1',
                'key2' => 'override2',
                'key3' => array(
                    'deep_key3' => 'deep3',
                    'deep_key4' => 'override4',
                    'deep_insert4' => 'insert4',
                ),
                'insert2' => 'insert2',
                'insert3' => 'insert3',
            ),
            $config->getRoot());
    }
}
