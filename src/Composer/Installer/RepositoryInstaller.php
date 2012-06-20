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

namespace Composer\Installer;

use Composer\IO\IOInterface;
use Composer\Config;
use Composer\Repository\RepositoryManager;
use Composer\Autoload\AutoloadGenerator;
use Composer\Downloader\DownloadManager;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Package\PackageInterface;

/**
 * Installer installation manager.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class RepositoryInstaller extends LibraryInstaller
{
    private $repositoryManager;
    private static $classCounter = 0;

    /**
     * @param string              $vendorDir         relative path for packages home
     * @param string              $binDir            relative path for binaries
     * @param DownloadManager     $dm                download manager
     * @param IOInterface         $io                io instance
     * @param InstallationManager $im                installation manager
     * @param array               $localRepositories array of InstalledRepositoryInterface
     */
    public function __construct($vendorDir, $binDir, DownloadManager $dm, IOInterface $io, RepositoryManager $rm, array $localRepositories, Config $config)
    {
        parent::__construct($vendorDir, $binDir, $dm, $io, 'composer-repository');
        $this->repositoryManager = $rm;

        foreach ($config->get('installers') as $componentDef) {
            $loader = new \Composer\Package\Loader\ArrayLoader();
            $componentPackage = $loader->load($componentDef);
            $targetDir = $componentPackage->getTargetDir();
            $installPath = $config->get('vendorDir') . '/' . $componentPackage->getPrettyName() . ($targetDir ? '/'.$targetDir : '');
            $dm->download($installerPackage, $this->getInstallPath($installerPackage));

//            if ('composer-repository' === $installerPackage->getType()) {
                $this->registerRepositoryType($installerPackage);
//            }
        }


        foreach ($localRepositories as $repo) {
            foreach ($repo->getPackages() as $package) {
                if ('composer-repository' === $package->getType()) {
                    $this->registerRepositoryType($package);
                }
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $extra = $package->getExtra();
        if (empty($extra['class'])) {
            throw new \UnexpectedValueException('Error while installing '.$package->getPrettyName().', composer-repository packages should have a class defined in their extra key to be usable.');
        }

        parent::install($repo, $package);
        $this->registerRepositoryType($package);
    }

    /**
     * {@inheritDoc}
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        $extra = $target->getExtra();
        if (empty($extra['class'])) {
            throw new \UnexpectedValueException('Error while installing '.$target->getPrettyName().', composer-repository packages should have a class defined in their extra key to be usable.');
        }

        parent::update($repo, $initial, $target);
        $this->registerRepositoryType($target);
    }

    private function registerRepositoryType(PackageInterface $package)
    {
        $downloadPath = $this->getInstallPath($package);

        $composer = new \Composer\Json\JsonFile($downloadPath . '/composer.json');
        $composer = $composer->read();

        $package->setAutoload($composer['autoload']);

        $extra = $composer['extra'];
        $repositoryType = $extra['repository-type'];
        $class = $extra['class'];

        $generator = new AutoloadGenerator;
        $map = $generator->parseAutoloads(array(array($package, $downloadPath)));
        $classLoader = $generator->createLoader($map);
        $classLoader->register();

        $this->repositoryManager->setRepositoryClass($repositoryType, $class);
    }
}
