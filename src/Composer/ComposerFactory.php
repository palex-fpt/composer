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
use Composer\Installer\InstallationManager;
use Composer\Downloader\DownloadManager;
use Composer\IO\IOInterface;
use Composer\Repository\RepositoryManager;
use Composer\Util\ProcessExecutor;
use Composer\Util\RemoteFilesystem;

/**
 * Creates an configured instance of configuration.
 *
 * Composer config merges from two parts:
 *  built-in config:            src/config.json
 *  home-dir config overrides:  {$home-dir}/config.json
 *  root package overrides:     {$cwd}/composer.json config section
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Igor Wiedler <igor@wiedler.ch>
 * @author Alexey Prilipko <palex@farpost.com>
 */
class ComposerFactory
{
    /**
     * Creates a Composer instance
     *
     * @param  IOInterface $io          IO instance
     * @param  mixed       $localConfig either a configuration array or a filename to read from, if null it will read from the default filename
     * @return Composer
     */
    public function createComposer(IOInterface $io, DependencyContainer $container)
    {
        $container->getInstance('composer');
        $lockFile = new JsonFile('composer.lock');

        $rfs = new RemoteFilesystem($io);
        $config = ConfigFactory::createConfig($composerFile);
        $repositoryManager = new RepositoryManager($io);
        $rootPackageLoader = new \Composer\Package\Loader\RootPackageLoader($composerFile);
        $rootPackage = $rootPackageLoader->load($composerFile);
        $locker = new \Composer\Package\Locker($lockFile);
        $downloadManager = new DownloadManager();
        $installationManager = new InstallationManager();

        return new Composer(
            $config,
            $rootPackage,
            $locker,
            $repositoryManager,
            $downloadManager,
            $installationManager
        );

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
}
