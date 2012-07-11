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
 *
 * Config allows combine several config sources into one configuration
 * There is special handling for 'repositories' section.
 * It is moved to 'config' section and stripped from keys, that has corresponding { "name": false } markers
 */
class Config
{
    private $config;

    /**
     * @param array $config1,... list of configuration data
     */
    public function __construct($config1 = array())
    {
        $resultConfig = array('config' => array());

        foreach (func_get_args() as $config) {
            $resultConfig = array_replace_recursive($resultConfig, $config);
        }

        $resultConfig = $this->moveAndCompactRepositories($resultConfig);

        $this->config = $resultConfig;
    }

    public function getRoot()
    {
        return $this->config;
    }

    public function getParameters()
    {
        $result = array();
        foreach ($this->config['config'] as $key => $value) {
            if (!is_array($value)) {
                $result[$key] = $this->getParameter($key);
            }
        }

        return $result;
    }

    private function getParameter($key)
    {
        return isset($this->config['config'][$key]) ? rtrim($this->process($this->config['config'][$key]), '/\\') : '';
    }

    public function getObject($key)
    {
        return isset($this->config['config'][$key]) ? $this->config['config'][$key] : array();
    }

    /**
     * Returns a setting
     *
     * @param  string $key
     * @return mixed
     */
    public function get($key)
    {
        return $this->getParameter($key);
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
     * @param  string $value a config string that can contain {$refs-to-other-config}
     * @return string
     */
    private function process($value)
    {
        $config = $this;

        return preg_replace_callback('#\{\$(.+)\}#', function ($match) use ($config) {
            return $config->get($match[1]);
        }, $value);
    }

    private function moveAndCompactRepositories(array $config)
    {
        $defaultRepositories = isset($config['config']['default-repositories']) ? $config['config']['default-repositories'] : array();
        $rootRepositories = isset($config['repositories']) ? $config['repositories'] : array();

        $overrides = array_intersect_key($defaultRepositories, $rootRepositories);
        $nonoverridedDefaults = array_diff_key($defaultRepositories, $overrides);
        $repositories = array_merge($rootRepositories, $nonoverridedDefaults);

        foreach ($repositories as $name => $repository) {
            // disable a repository by name
            if (false === $repository) {
                unset($repositories[$name]);
                continue;
            }
            // disable a repository with an anonymous marker {"name": false} repo
            if (1 === count($repository) && false === current($repository)) {
                $disabledRepoName = key($repository);
                unset($repositories[$disabledRepoName]); // remove disabled repo
                unset($repositories[$name]); // remove marker
                continue;
            }
        }

        // move repositories to config section
        unset($config['repositories']);
        $config['config']['repositories'] = $repositories;

        return $config;
    }
}
