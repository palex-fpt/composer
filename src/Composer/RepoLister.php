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
use Composer\IO\IOInterface;
use Composer\Repository\RepositoryManager;
use Composer\Package\PackageInterface;
use Symfony\Component\Console\Input\InputInterface;
use Composer\Repository\CompositeRepository;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositoryInterface;

/**
 * @author Robert Schönthal <seroscho@googlemail.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class RepoLister
{
    private $io;
    private $localRepository;
    private $localDevRepository;
    private $platformRepository;
    private $repositoryManager;

    public function __construct(
        IOInterface $io,
        RepositoryInterface $localRepository,
        RepositoryInterface $localDevRepository,
        RepositoryInterface $platformRepository,
        RepositoryManager $repositoryManager
    )
    {
        $this->io = $io;
        $this->localRepository = $localRepository;
        $this->localDevRepository = $localDevRepository;
        $this->platformRepository = $platformRepository;
        $this->repositoryManager = $repositoryManager;
    }

    public function showPackage($platform, $installed,  $packageArg, $versionArg)
    {
        $installedRepo = new CompositeRepository(array($this->localRepository, $this->localDevRepository, $this->platformRepository));
        if ($platform) {
            $repos = $this->platformRepository;
        } elseif ($installed) {
            $repos = $installedRepo;
        } else {
            $repos = new CompositeRepository(array($this->localRepository, $this->localDevRepository, $this->platformRepository), $this->repositoryManager->getRepositories());
        }

        // show single package or single version
        $package = $this->getPackage($installedRepo, $repos, $packageArg, $versionArg);
        if (!$package) {
            throw new \InvalidArgumentException('Package '.$packageArg.' not found');
        }

        $this->printMeta($package, $installedRepo, $repos, $versionArg);
        $this->printLinks($package, 'requires');
        $this->printLinks($package, 'devRequires', 'requires (dev)');
        if ($package->getSuggests()) {
            $this->io->write("\n<info>suggests</info>");
            foreach ($package->getSuggests() as $suggested => $reason) {
                $this->io->write($suggested . ' <comment>' . $reason . '</comment>');
            }
        }
        $this->printLinks($package, 'provides');
        $this->printLinks($package, 'conflicts');
        $this->printLinks($package, 'replaces');

        return;
    }

    public function showList($platform, $installed)
    {
        $installedRepo = new CompositeRepository(array($this->localRepository, $this->localDevRepository, $this->platformRepository));
        if ($platform) {
            $repos = $this->platformRepository;
        } elseif ($installed) {
            $repos = $installedRepo;
        } else {
            $repos = new CompositeRepository(array($this->localRepository, $this->localDevRepository, $this->platformRepository), $this->repositoryManager->getRepositories());
        }

        // list packages
        $packages = array();
        foreach ($repos->getPackages() as $package) {
            if ($this->platformRepository->hasPackage($package)) {
                $type = '<info>platform</info>:';
            } elseif ($installedRepo->hasPackage($package)) {
                $type = '<info>installed</info>:';
            } else {
                $type = '<comment>available</comment>:';
            }
            if (isset($packages[$type][$package->getName()])
                && version_compare($packages[$type][$package->getName()]->getVersion(), $package->getVersion(), '>=')
            ) {
                continue;
            }
            $packages[$type][$package->getName()] = $package;
        }

        foreach (array('<info>platform</info>:', '<comment>available</comment>:', '<info>installed</info>:') as $type) {
            if (isset($packages[$type])) {
                $this->io->write($type);
                ksort($packages[$type]);
                foreach ($packages[$type] as $package) {
                    $this->io->write('  '.$package->getPrettyName() .' <comment>:</comment> '. strtok($package->getDescription(), "\r\n"));
                }
                $this->io->write('');
            }
        }
    }

    private function populateRepositories($platform, $installed)
    {

    }

    /**
     * finds a package by name and version if provided
     *
     * @param  InputInterface            $input
     * @return PackageInterface
     * @throws \InvalidArgumentException
     */
    protected function getPackage(RepositoryInterface $installedRepo, RepositoryInterface $repos, $packageArg, $versionArg)
    {
        // we have a name and a version so we can use ::findPackage
        if ($versionArg) {
            return $repos->findPackage($packageArg, $versionArg);
        }

        // check if we have a local installation so we can grab the right package/version
        foreach ($installedRepo->getPackages() as $package) {
            if ($package->getName() === $packageArg) {
                return $package;
            }
        }

        // we only have a name, so search for the highest version of the given package
        $highestVersion = null;
        foreach ($repos->findPackages($packageArg) as $package) {
            if (null === $highestVersion || version_compare($package->getVersion(), $highestVersion->getVersion(), '>=')) {
                $highestVersion = $package;
            }
        }

        return $highestVersion;
    }

    /**
     * prints package meta data
     */
    protected function printMeta(PackageInterface $package, RepositoryInterface $installedRepo, RepositoryInterface $repos, $versionArg)
    {
        $this->io->write('<info>name</info>     : ' . $package->getPrettyName());
        $this->io->write('<info>descrip.</info> : ' . $package->getDescription());
        $this->io->write('<info>keywords</info> : ' . join(', ', $package->getKeywords() ?: array()));
        $this->printVersions($package, $installedRepo, $repos, $versionArg);
        $this->io->write('<info>type</info>     : ' . $package->getType());
        $this->io->write('<info>license</info>  : ' . implode(', ', $package->getLicense()));
        $this->io->write('<info>source</info>   : ' . sprintf('[%s] <comment>%s</comment> %s', $package->getSourceType(), $package->getSourceUrl(), $package->getSourceReference()));
        $this->io->write('<info>dist</info>     : ' . sprintf('[%s] <comment>%s</comment> %s', $package->getDistType(), $package->getDistUrl(), $package->getDistReference()));
        $this->io->write('<info>names</info>    : ' . implode(', ', $package->getNames()));

        if ($package->getSupport()) {
            $this->io->write("\n<info>support</info>");
            foreach ($package->getSupport() as $type => $value) {
                $this->io->write('<comment>' . $type . '</comment> : '.$value);
            }
        }

        if ($package->getAutoload()) {
            $this->io->write("\n<info>autoload</info>");
            foreach ($package->getAutoload() as $type => $autoloads) {
                $this->io->write('<comment>' . $type . '</comment>');

                if ($type === 'psr-0') {
                    foreach ($autoloads as $name => $path) {
                        $this->io->write(($name ?: '*') . ' => ' . ($path ?: '.'));
                    }
                } elseif ($type === 'classmap') {
                    $this->io->write(implode(', ', $autoloads));
                }
            }
            if ($package->getIncludePaths()) {
                $this->io->write('<comment>include-path</comment>');
                $this->io->write(implode(', ', $package->getIncludePaths()));
            }
        }
    }

    /**
     * prints all available versions of this package and highlights the installed one if any
     */
    protected function printVersions(PackageInterface $package, RepositoryInterface $installedRepo, RepositoryInterface $repos, $versionArg)
    {
        if ($versionArg) {
            $this->io->write('<info>version</info>  : ' . $package->getPrettyVersion());

            return;
        }

        $versions = array();

        foreach ($repos->findPackages($package->getName()) as $version) {
            $versions[$version->getPrettyVersion()] = $version->getVersion();
        }

        uasort($versions, 'version_compare');

        $versions = implode(', ', array_keys(array_reverse($versions)));

        // highlight installed version
        if ($installedRepo->hasPackage($package)) {
            $versions = str_replace($package->getPrettyVersion(), '<info>* ' . $package->getPrettyVersion() . '</info>', $versions);
        }

        $this->io->write('<info>versions</info> : ' . $versions);
    }

    /**
     * print link objects
     *
     * @param string $linkType
     */
    protected function printLinks(PackageInterface $package, $linkType, $title = null)
    {
        $title = $title ?: $linkType;
        if ($links = $package->{'get'.ucfirst($linkType)}()) {
            $this->io->write("\n<info>" . $title . "</info>");

            foreach ($links as $link) {
                $this->io->write($link->getTarget() . ' <comment>' . $link->getPrettyConstraint() . '</comment>');
            }
        }
    }
}
