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

use Composer\Package\PackageInterface;
use Composer\IO\IOInterface;
use Composer\Package\Locker;
use Composer\Repository\RepositoryManager;
use Composer\Installer\InstallationManager;
use Composer\Downloader\DownloadManager;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Konstantin Kudryashiv <ever.zet@gmail.com>
 */
class ComponentLoader
{
    private $composer;
    private $io;

    public function __construct(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    private function createInstance($className, $registry = array()) {
        $reflectionClass = new \ReflectionClass($className);
        $args = array();

        if ($constructor = $reflectionClass->getConstructor()) {
            foreach ($constructor->getParameters() as $parameter) {
                $argType = $parameter->getClass();
                if ($argType && array_key_exists($argType->getName(), $registry)) {
                    $args[] = $registry[$argType->getName()];
                } elseif ($parameter->allowsNull()) {
                    $args[] = null;
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $args[] = $parameter->getDefaultValue();
                } else {
                    throw new \InvalidArgumentException(sprintf('Can not instaniate %s. Parameter %s can not be provided.', $className, $parameter->getName()));
                }
            }
        }

        return $reflectionClass->newInstanceArgs($args);
    }

    public function initializeWithAvailableClasses(Config $config, $registry)
    {
        $composer = $this->composer;
        $setup = array(
            'repository-factories' => function ($type, $component) use($composer) { $composer->getRepositoryManager()->setRepositoryFactory($type, $component); },
            'installers' => function ($type, $component) use($composer) { $composer->getInstallationManager()->addInstaller($component); },
            'downloaders' => function ($type, $component) use($composer) { $composer->getDownloadManager()->setDownloader($type, $component); },
        );
        $unavailable = array(
            'repository-factories' => array(),
            'installers' => array(),
            'downloaders' => array()
        );
        foreach ($setup as $entryType => $initializer) {
            foreach ($config->get($entryType) as $type => $className) {
                // entry can be overriden by local config
                if (false === $className) {
                    continue;
                }

                if (class_exists($className)) {
                    $component = $this->createInstance($className, $registry);
                } else {
                    $unavailable[$entryType][$type] = $className;
                }

                $initializer($type, $component);
            }
        }

        return $unavailable;
    }

    public function loadComponentRepositories(Config $config) {
        if (isset($config['component-repositories'])) {
            foreach ($config['component-repositories'] as $index => $repo) {
                if (!is_array($repo)) {
                    throw new \UnexpectedValueException('Repository '.$index.' should be an array, '.gettype($repo).' given');
                }
                if (!isset($repo['type'])) {
                    throw new \UnexpectedValueException('Repository '.$index.' ('.json_encode($repo).') must have a type defined');
                }
                $repository = $this->composer->getRepositoryManager()->createRepository($repo['type'], $repo);
                $this->composer->getRepositoryManager()->addRepository($repository);
            }
//            $package->setRepositories($config['repositories']);
        }
    }

    private function loadPackage($packageConfig) {
        $loader = new \Composer\Package\Loader\ArrayLoader();
        $componentPackage = $loader->load($packageConfig);
        $targetDir = $componentPackage->getTargetDir();
        $installPath = $this->composer->getConfig()->get('vendor-dir') . '/' . $componentPackage->getPrettyName() . ($targetDir ? '/'.$targetDir : '');
        $this->composer->getDownloadManager()->download($componentPackage, $installPath);

        $packageJson = new \Composer\Json\JsonFile($installPath. '/composer.json');
        $packageJson = $packageJson->read();

        $extra = $packageJson['extra'];
        $class = $extra['class'];

        $componentPackage->setAutoload($packageJson['autoload']);

        $generator = new \Composer\Autoload\AutoloadGenerator();
        $map = $generator->parseAutoloads(array(array($componentPackage, $installPath)));
        $classLoader = $generator->createLoader($map);
        $classLoader->register();

        return $class;
    }

    public function loadComponent($componentConfig)
    {
        if (isset($componentConfig['package'])) {
            $class = $this->loadPackage($componentConfig['package'], $composer, $config, $io);
        } elseif (isset($componentConfig['class'])) {
            $class = $componentConfig['class'];
        } else {
            throw new \InvalidArgumentException('component must define class or package key');
        }

        // instaniate component
        $component = new $class($this->composer, $this->io);
        return $component;
    }
}
