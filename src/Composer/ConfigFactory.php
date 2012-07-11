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

namespace Composer;

use Composer\Json\JsonFile;

/**
 * Creates an configured instance of configuration.
 *
 * Composer config merges from two parts:
 *  built-in config:            src/config.json
 *  home-dir config overrides:  {$home-dir}/config.json
 *  root package overrides:     {$cwd}/composer.json config section
 *
 * and from environment variables with names like 'COMPOSER_*':
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Igor Wiedler <igor@wiedler.ch>
 * @author Alexey Prilipko <palex@farpost.com>
 */
class ConfigFactory
{
    /**
     * @param  string $composerFile pathname composer.json file
     * @return Config
     */
    public static function createConfig()
    {
        $envParameters = self::readEnvVars();

        // load main Composer configuration
        if (isset($envParameters['home'])) {
            $home = $envParameters['home'];
        } elseif (defined('PHP_WINDOWS_VERSION_MAJOR')) {
            $home = getenv('APPDATA') . '/Composer';
        } else {
            $home = getenv('HOME') . '/.composer';
        }
        $envParameters = array('config' => $envParameters);

        // Protect directory against web access
        if (!file_exists($home . '/.htaccess')) {
            if (!is_dir($home)) {
                @mkdir($home, 0777, true);
            }
            @file_put_contents($home . '/.htaccess', 'Deny from all');
        }

        // load defaults
        $defaultConfig = new JsonFile(__DIR__.'/config.json');
        $defaults = array('config' => $defaultConfig->read());

        // load home overrides
        $file = new JsonFile($home.'/config.json');
        if ($file->exists()) {
            $config = $file->read();
            $globalOverrides = array('config' => $config);
        } else {
            $globalOverrides = array();
        }

        // load {cwd}/composer.json overrides
        $composerFile = new JsonFile('composer.json');
        $composerFileSettings = $composerFile->read();
        $localOverrides = $composerFileSettings;

        return new Config(
            $defaults,
            $globalOverrides,
            $localOverrides,
            $envParameters
        );
    }

    private static function readEnvVars()
    {
        $result = array();
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'COMPOSER_') === 0) {
                $name = substr($key, strlen('COMPOSER_'));
                $name = strtolower($name);
                $name = strtr($name, '_', '-');
                $result[$name] = $value;
            }
        }

        return $result;
    }
}
