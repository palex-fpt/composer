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
use Composer\IO\IOInterface;
use Composer\Repository\ComposerRepository;
use Composer\Repository\RepositoryManager;
use Composer\Util\ProcessExecutor;
use Composer\Util\RemoteFilesystem;

/**
 * Creates an configured instance of composer.
 *
 * @author Ryan Weaver <ryan@knplabs.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class Factory
{
    /**
     * @return Config
     */
    public static function createConfig()
    {
        // load main Composer configuration
        if (!$home = getenv('COMPOSER_HOME')) {
            if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
                $home = getenv('APPDATA') . '/Composer';
            } else {
                $home = getenv('HOME') . '/.composer';
            }
        }

        // Protect directory against web access
        if (!file_exists($home . '/.htaccess')) {
            if (!is_dir($home)) {
                @mkdir($home, 0777, true);
            }
            @file_put_contents($home . '/.htaccess', 'Deny from all');
        }

        $config = new Config();

        // add home dir to the config
        $config->merge(array('config' => array('home' => $home)));

        $file = new JsonFile($home.'/config.json');
        if ($file->exists()) {
            $config->merge($file->read());
        }

        return $config;
    }

    public function getComposerFile()
    {
        return getenv('COMPOSER') ?: 'composer.json';
    }

    public static function createDefaultRepositories(IOInterface $io = null, Config $config = null, RepositoryManager $rm = null)
    {
        $repos = array();

        if (!$config) {
            $config = static::createConfig();
        }
        if (!$rm) {
            if (!$io) {
                throw new \InvalidArgumentException('This function requires either an IOInterface or a RepositoryManager');
            }
            $factory = new static;
            $rm = $factory->createRepositoryManager($io, $config);
        }

        foreach ($config->getRepositories() as $index => $repo) {
            if (!is_array($repo)) {
                throw new \UnexpectedValueException('Repository '.$index.' ('.json_encode($repo).') should be an array, '.gettype($repo).' given');
            }
            if (!isset($repo['type'])) {
                throw new \UnexpectedValueException('Repository '.$index.' ('.json_encode($repo).') must have a type defined');
            }
            $name = is_int($index) && isset($repo['url']) ? preg_replace('{^https?://}i', '', $repo['url']) : $index;
            while (isset($repos[$name])) {
                $name .= '2';
            }
            $repos[$name] = $rm->createRepository($repo['type'], $repo);
        }

        return $repos;
    }

    private function createComposerFile()
    {
        return getenv('COMPOSER') ?: 'composer.json';

    }

    /**
     * Creates a Composer instance
     *
     * @param IOInterface       $io          IO instance
     * @param array|string|null $localConfig either a configuration array or a filename to read from, if null it will
     *                                       read from the default filename
     * @throws \InvalidArgumentException
     * @return Composer
     */
    public function createComposer(IOInterface $io, $localConfig = null)
    {
        $rfs = new RemoteFilesystem($io);

        $configFactory = new ConfigFactory();
        $config = $configFactory->createConfig($composerFile);

        // load Composer configuration
        if (null === $localConfig) {
            $localConfig = $this->getComposerFile();
        }

        if (is_string($localConfig)) {
            $composerFile = $localConfig;
            $file = new JsonFile($localConfig, $rfs);

            if (!$file->exists()) {
                if ($localConfig === 'composer.json') {
                    $message = 'Composer could not find a composer.json file in '.getcwd();
                } else {
                    $message = 'Composer could not find the config file: '.$localConfig;
                }
                $instructions = 'To initialize a project, please create a composer.json file as described in the http://getcomposer.org/ "Getting Started" section';
                throw new \InvalidArgumentException($message.PHP_EOL.$instructions);
            }

            $file->validateSchema(JsonFile::LAX_SCHEMA);
            $localConfig = $file->read();
        }

        // Configuration defaults
        $config = static::createConfig();
        $config->merge($localConfig);

        // special repository merging
        foreach ($config->get('default-repositories') as $repository) {
            if (false !== $repository) {
                $localConfig['repositories'][] = $repository;
            }
        }

        // setup process timeout
        ProcessExecutor::setTimeout((int) $config->get('process-timeout'));

        $dm = new \Composer\Downloader\DownloadManager();
        $im = $this->createInstallationManager();
        $rm = new RepositoryManager($io, $config);

        // load local repository
        $this->addLocalRepository($rm, $vendorDir);

        // load package
        $loader  = new Package\Loader\RootPackageLoader($rm, $config);
        $package = $loader->load($localConfig);

        // initialize download manager
        $dm = $this->createDownloadManager($io);

        // initialize installation manager
        $im = $this->createInstallationManager($rm, $dm, $vendorDir, $binDir, $io);

        // purge packages if they have been deleted on the filesystem
        $this->purgePackages($rm, $im);

        // initialize composer
        $composer = new Composer();
        $composer->setConfig($config);
        $composer->setRepositoryManager($rm);
        $composer->setDownloadManager($dm);
        $composer->setInstallationManager($im);

        // load local repository
        $this->addLocalRepository($rm, $config);

        $componentLoader = new ComponentLoader($composer, $io);

        // list of objects available to components constructors
        $registry = array(
            'Composer\IO\IOInterface' => $io,
            'Composer\Util\RemoteFilesystem' => $rfs,
            'Composer\Util\Filesystem' => new Util\Filesystem(),
            'Composer\Config' => $config,
            'Composer\Installer\InstallationManager' => $im,
            'Composer\Repository\RepositoryManager' => $rm,
            'Composer\Downloader\DownloadManager' => $dm,
        );

        // step one: initialize repositories, installers and downloaders with local available classes
        $unavailable = $componentLoader->initializeWithAvailableClasses($config, $registry);

        // step two: update components
        if (0 != count($unavailable['repository-factories']) + count($unavailable['installers']) + count($unavailable['downloaders'])) {
            throw new \InvalidArgumentException('Some Composer components are unavailable.');
            // todo: Load components and reinitialize repositories, installers and downloadres with available classes
        }

        // init locker if possible
        if (isset($composerFile)) {
            $lockFile = "json" === pathinfo($composerFile, PATHINFO_EXTENSION)
                ? substr($composerFile, 0, -4).'lock'
                : $composerFile . '.lock';
            $locker = new Package\Locker(new JsonFile($lockFile, $rfs), $rm, md5_file($composerFile));
            $composer->setLocker($locker);
        }

         // load package
        $loader  = new Package\Loader\RootPackageLoader($rm);
        $package = $loader->load($localConfig);

        // purge packages if they have been deleted on the filesystem
        $this->purgePackages($rm, $im);

        $composer->setPackage($package);

        return $composer;
    }

    protected function addLocalRepository(RepositoryManager $rm, Config $config)
    {
        $vendorDir = $config->get('vendor-dir');

        $rm->setLocalRepository(new Repository\InstalledFilesystemRepository(new JsonFile($vendorDir.'/composer/installed.json')));
        $rm->setLocalDevRepository(new Repository\InstalledFilesystemRepository(new JsonFile($vendorDir.'/composer/installed_dev.json')));
    }

    protected function purgePackages(Repository\RepositoryManager $rm, Installer\InstallationManager $im)
    {
        foreach ($rm->getLocalRepositories() as $repo) {
            /* @var $repo   Repository\WritableRepositoryInterface */
            foreach ($repo->getPackages() as $package) {
                if (!$im->isPackageInstalled($repo, $package)) {
                    $repo->removePackage($package);
                }
            }
        }
    }

    protected function createInstallationManager()
    {
        return new \Composer\Installer\InstallationManager();
    }

    /**
     * @param IOInterface $io     IO instance
     * @param mixed       $config either a configuration array or a filename to read from, if null it will read from
     *                             the default filename
     * @return Composer
     */
    public static function create(IOInterface $io, $config = null)
    {
        $factory = new static();

        return $container->getInstance('composer');
        return $factory->createComposer($io, $config);
    }
}
