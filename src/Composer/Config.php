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

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Config
{
    private $config;

    public function __construct()
    {
        $resultConfig = array();

        foreach (func_get_args() as $config) {
            $resultConfig = array_replace_recursive($resultConfig, $config);
        }

        $this->config = $resultConfig;
    }

    /**
     * Returns a setting
     *
     * @param  string $key
     * @return mixed
     */
    public function get($key)
    {
        switch ($key) {
            case 'vendor-dir':
            case 'bin-dir':
            case 'component-dir':
            case 'process-timeout':
                // convert foo-bar to COMPOSER_FOO_BAR and check if it exists since it overrides the local config
                $env = 'COMPOSER_' . strtoupper(strtr($key, '-', '_'));

                return $this->process(getenv($env) ?: $this->config[$key]);

            case 'home':
                return rtrim($this->process($this->config[$key]), '/\\');

            case 'downloaders':
            case 'installers':
            case 'repository-factories':
            case 'default-repositories':
                return $this->config[$key];

            default:
                return $this->process($this->config[$key]);
        }
    }

    public function getArray()
    {
        return $this->config;
    }

    /**
     * Checks whether a setting exists
     *
     * @param  string $key
     * @return bool
     */
    public function has($key)
    {
        return array_key_exists($key, $this->config);
    }

    /**
     * Replaces {$refs} inside a config string
     *
     * @param string a config string that can contain {$refs-to-other-config}
     * @return string
     */
    private function process($value)
    {
        $config = $this;

        return preg_replace_callback('#\{\$(.+)\}#', function ($match) use ($config) {
            return $config->get($match[1]);
        }, $value);
    }
}
