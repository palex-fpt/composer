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

use Composer\Composer;
use Composer\Autoload\AutoloadGenerator;
use Composer\Script\EventDispatcher;
use Composer\Repository\PlatformRepository;
use Composer\Downloader\PharDownloader;
use Composer\Downloader\TarDownloader;
use Composer\Downloader\ZipDownloader;
use Composer\Downloader\FileDownloader;
use Composer\Downloader\HgDownloader;
use Composer\Downloader\SvnDownloader;
use Composer\Downloader\GitDownloader;
use Composer\Repository\PearRepositoryFactory;
use Composer\Repository\PackageRepositoryFactory;
use Composer\Repository\VcsRepositoryFactory;
use Composer\Repository\ComposerRepositoryFactory;
use Composer\Installer\MetapackageInstaller;
use Composer\Installer\InstallerInstaller;
use Composer\Installer\LibraryInstaller;
use Composer\Util\Filesystem;
use Composer\Json\JsonFile;
use Composer\Repository\InstalledFilesystemRepository;
use Composer\Util\RemoteFilesystem;
use Composer\Downloader\DownloadManager;
use Composer\Installer\InstallationManager;
use Composer\Repository\RepositoryManager;
use Composer\Package\Locker;
use Composer\Util\ProcessExecutor;
use Composer\Package\Version\VersionParser;
use Composer\Package\Loader\RootPackageLoader;
use Composer\Config;
use Composer\IO\IOInterface;

/**
 * Dependency container
 *
 * @author Alexey Prilipko <palex@farpost.com>
 */
class Container
{
    private $parameters;
    protected $singletons;
    /**
     * services
     */
    public function __construct()
    {
        $this->singletons = new \ArrayObject(array());
    }

    public function getComposer()
    {
        return isset($this->singletons[__METHOD__]) ? $this->singletons[__METHOD__] : $this->singletons[__METHOD__] =
            new Composer(
            $this->getConfig(),
            $this->getRootPackage(),
            $this->getLocker(),
            $this->getRepositoryManager(),
            $this->getDownloadManager(),
            $this->getInstallationManager(),
            $this->getRemoteFilesystem()
        );
    }

    public function getRepoLister()
    {
        return new RepoLister(
            $this->getIO(),
            $this->getLocalRepository(),
            $this->getLocalDevRepository(),
            $this->getPlatformRepository(),
            $this->getRepositoryManager()
        );
    }

    public function getInstaller()
    {
        return new Installer(
            $this->getIO(),
            $this->getConfig(),
            $this->getRootPackage(),
            $this->getDownloadManager(),
            $this->getRepositoryManager(),
            $this->getLocker(),
            $this->getInstallationManager(),
            $this->getEventDispatcher(),
            $this->getAutoloadGenerator()
        );
    }

    /**
     * Utils
     */

    protected function getParameter($key)
    {
        return isset($this->parameters[$key]) ? $this->parameters[$key] : null;
    }

    public function setParameter($key, $value)
    {
        return $this->parameters[$key] = $value;
    }

    /**
     * intermediate objects
     */

    /**
     * @return Config
     */
    protected function getConfig()
    {
        return $this->getParameter('config');
    }

    /**
     * @return IOInterface
     */
    protected function getIO()
    {
        return $this->getParameter('io');
    }

    protected function getRootPackage()
    {
        $loader = $this->getRootPackageLoader();
        return $loader->load($this->getConfig());
    }

    protected function getRootPackageLoader()
    {
        return new RootPackageLoader(
            $this->getConfig(),
            $this->getVersionParser(),
            $this->getProcessExecutor()
        );
    }

    protected function getVersionParser()
    {
        return new VersionParser();
    }

    protected function getProcessExecutor()
    {
        return new ProcessExecutor();
    }

    protected function getRepositoryManager()
    {
        return isset($this->singletons[__METHOD__]) ? $this->singletons[__METHOD__] : $this->singletons[__METHOD__] =
            new RepositoryManager(
            $this->getLocalRepository(),
            $this->getLocalDevRepository(),
            $this->getRepositoryFactories(),
            $this->getConfig()
        );
    }

    protected function getInstallationManager()
    {
        return isset($this->singletons[__METHOD__]) ? $this->singletons[__METHOD__] : $this->singletons[__METHOD__] =
            new InstallationManager(
            $this->getInstallers()
        );
    }

    protected function getInstallers()
    {
        return array(
            "library" => $this->getLibraryInstaller(),
            "composer-installer" => $this->getInstallerInstaller(),
            "metapackage" => $this->getMetapackageInstaller(),
            "" => $this->getDefaultInstaller(),
        );
    }

    protected function getLibraryInstaller()
    {
        return new LibraryInstaller(
            $this->getIO(),
            $this->getDownloadManager(),
            $this->getConfig()
        );
    }

    protected function getInstallerInstaller()
    {
        return new InstallerInstaller(
            $this->getIO(),
            $this->getRepositoryManager(),
            $this->getDownloadManager(),
            $this->getConfig()
        );
    }

    protected function getMetapackageInstaller()
    {
        return new MetapackageInstaller(
        );
    }

    protected function getDefaultInstaller()
    {
        return new LibraryInstaller(
            $this->getIO(),
            $this->getDownloadManager(),
            $this->getConfig()
        );
    }

    protected function getRepositoryFactories()
    {
        return array(
            "composer" => $this->getComposerRepositoryFactory(),
            "vcs" => $this->getVcsRepositoryFactory(),
            "git" => $this->getVcsRepositoryFactory(),
            "svn" => $this->getVcsRepositoryFactory(),
            "hg" => $this->getVcsRepositoryFactory(),
            "package" => $this->getPackageRepositoryFactory(),
            "pear" => $this->getPearRepositoryFactory(),
        );
    }

    protected function getComposerRepositoryFactory()
    {
        return new ComposerRepositoryFactory(
            $this->getIO(),
            $this->getConfig()
        );
    }

    protected function getVcsRepositoryFactory()
    {
        return new VcsRepositoryFactory(
            $this->getIO(),
            $this->getConfig()
        );
    }

    protected function getPackageRepositoryFactory()
    {
        return new PackageRepositoryFactory(
            $this->getIO(),
            $this->getConfig(),
            $this->getRemoteFilesystem()
        );
    }

    protected function getPearRepositoryFactory()
    {
        return new PearRepositoryFactory(
            $this->getIO(),
            $this->getRemoteFilesystem()
        );
    }

    protected function getDownloadManager()
    {
        return isset($this->singletons[__METHOD__]) ? $this->singletons[__METHOD__] : $this->singletons[__METHOD__] =
            new DownloadManager(
            $this->getParameter('prefer-source'),
            $this->getFilesystem(),
            $this->getDownloaders()
        );
    }

    protected function getDownloaders()
    {
        return array(
            "git" => $this->getGitDownloader(),
            "svn" => $this->getSvnDownloader(),
            "hg" => $this->getHgDownloader(),
            "zip" => $this->getZipDownloader(),
            "tar" => $this->getTarDownloader(),
            "phar" => $this->getPharDownloader(),
            "file" => $this->getFileDownloader(),
        );
    }

    protected function getGitDownloader()
    {
        return new GitDownloader(
            $this->getIO(),
            $this->getProcessExecutor(),
            $this->getFilesystem()
        );
    }

    protected function getSvnDownloader()
    {
        return new SvnDownloader(
            $this->getIO(),
            $this->getProcessExecutor(),
            $this->getFilesystem()
        );
    }

    protected function getHgDownloader()
    {
        return new HgDownloader(
            $this->getIO(),
            $this->getProcessExecutor(),
            $this->getFilesystem()
        );
    }

    protected function getFileDownloader()
    {
        return new FileDownloader(
            $this->getIO(),
            $this->getRemoteFilesystem(),
            $this->getFilesystem()
        );
    }

    protected function getZipDownloader()
    {
        return new ZipDownloader(
            $this->getIO(),
            $this->getProcessExecutor()
        );
    }

    protected function getTarDownloader()
    {
        return new TarDownloader(
            $this->getIO(),
            $this->getRemoteFilesystem(),
            $this->getFilesystem()
        );
    }

    protected function getPharDownloader()
    {
        return new PharDownloader(
            $this->getIO(),
            $this->getRemoteFilesystem(),
            $this->getFilesystem()
        );
    }

    protected function getLocker()
    {
        return new Locker(
            $this->getLockerLockFile(),
            $this->getRepositoryManager(),
            $this->getInstallationManager()
        );
    }

    protected function getLockerLockFile()
    {
        return new JsonFile(
            $this->getParameter('home') . "/composer.lock",
            $this->getRemoteFilesystem()
        );
    }

    protected function getFilesystem()
    {
        return new Filesystem(
        );
    }

    protected function getRemoteFilesystem()
    {
        return new RemoteFilesystem(
            $this->getIO()
        );
    }

    protected function getLocalRepository()
    {
        return new InstalledFilesystemRepository(
            $this->getLocalRepositoryJsonFile()
        );
    }

    protected function getLocalRepositoryJsonFile()
    {
        return new JsonFile(
            $this->getParameter('vendor-dir') . '/composer/installed.json',
            $this->getRemoteFilesystem()
        );
    }

    protected function getLocalDevRepository()
    {
        return new InstalledFilesystemRepository(
            $this->getLocalDevRepositoryJsonFile()
        );
    }

    protected function getPlatformRepository()
    {
        return new PlatformRepository(
        );
    }

    protected function getLocalDevRepositoryJsonFile()
    {
        return new JsonFile(
            $this->getParameter('vendor-dir') . '/composer/installed_dev.json',
            $this->getRemoteFilesystem()
        );
    }

    protected function getEventDispatcher()
    {
        return new EventDispatcher(
            $this->getComposer(),
            $this->getIO()
        );
    }

    protected function getAutoloadGenerator()
    {
        return new AutoloadGenerator(
        );
    }
}
