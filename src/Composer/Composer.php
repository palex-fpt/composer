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
use Composer\Util\RemoteFilesystem;
use Composer\Package\Locker;
use Composer\Repository\RepositoryManager;
use Composer\Installer\InstallationManager;
use Composer\Downloader\DownloadManager;

/**
 * It is service locator. It does not responsible for anything useful.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Konstantin Kudryashiv <ever.zet@gmail.com>
 */
class Composer
{
    const VERSION = '@package_version@';

    private $config;                // configuration
    private $package;               // root package
    private $locker;                // lock file reader/writer aka 'packages to be installed'

    private $repositoryManager;     // manages list of repositories. there are three main groups 'local-installed', 'local-dev-installed' and 'available'
    private $downloadManager;       // manages list of downloaders (tbh its package managers)
    private $installationManager;   // manages installers (post-download processors)
    private $autoloadGenerator;   // hides in installer?
    private $remoteFilesystem;    // hides in repositoryManager and downloadManager ?

    public function __construct(
        Config $config,
        PackageInterface $rootPackage,
        Locker $locker,
        RepositoryManager $repositoryManager,
        DownloadManager $downloadManager,
        InstallationManager $installationManager,
        RemoteFilesystem $remoteFilesystem
    )
    {
        $this->config = $config;
        $this->package = $rootPackage;
        $this->locker = $locker;
        $this->repositoryManager = $repositoryManager;
        $this->downloadManager = $downloadManager;
        $this->installationManager = $installationManager;
        $this->remoteFilesystem = $remoteFilesystem;

        $this->purgePackages($repositoryManager, $installationManager);
    }

    /**
     * @param  Package\PackageInterface $package
     * @return void
     */
    public function setPackage(PackageInterface $package)
    {
        $this->package = $package;
    }

    /**
     * @return Package\PackageInterface
     */
    public function getPackage()
    {
        return $this->package;
    }

    /**
     * @param Config $config
     */
    public function setConfig(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param Package\Locker $locker
     */
    public function setLocker(Locker $locker)
    {
        $this->locker = $locker;
    }

    /**
     * @return Package\Locker
     */
    public function getLocker()
    {
        return $this->locker;
    }

    /**
     * @param Repository\RepositoryManager $manager
     */
    public function setRepositoryManager(RepositoryManager $manager)
    {
        $this->repositoryManager = $manager;
    }

    /**
     * @return Repository\RepositoryManager
     */
    public function getRepositoryManager()
    {
        return $this->repositoryManager;
    }

    /**
     * @param Downloader\DownloadManager $manager
     */
    public function setDownloadManager(DownloadManager $manager)
    {
        $this->downloadManager = $manager;
    }

    /**
     * @return Downloader\DownloadManager
     */
    public function getDownloadManager()
    {
        return $this->downloadManager;
    }

    /**
     * @param Installer\InstallationManager $manager
     */
    public function setInstallationManager(InstallationManager $manager)
    {
        $this->installationManager = $manager;
    }

    /**
     * @return Installer\InstallationManager
     */
    public function getInstallationManager()
    {
        return $this->installationManager;
    }

    public function setRemoteFilesystem($remoteFilesystem)
    {
        $this->remoteFilesystem = $remoteFilesystem;
    }

    public function getRemoteFilesystem()
    {
        return $this->remoteFilesystem;
    }

    private function purgePackages(Repository\RepositoryManager $rm, Installer\InstallationManager $im)
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
}
