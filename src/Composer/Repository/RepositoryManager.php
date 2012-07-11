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

namespace Composer\Repository;

use Composer\Config;

/**
 * Repositories manager.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 * @author François Pluchino <francois.pluchino@opendisplay.com>
 */
class RepositoryManager
{
    private $localRepository;
    private $localDevRepository;
    private $repositories = array();
    /** @var RepositoryFactoryInterface[] */
    private $repositoryFactories = array();
    private $io;
    private $config;

    /**
     * @param RepositoryInterface          $localRepository
     * @param RepositoryInterface          $localDevRepository
     * @param RepositoryFactoryInterface[] $repositoryFactories
     */
    public function __construct(RepositoryInterface $localRepository, RepositoryInterface $localDevRepository, $repositoryFactories, Config $config)
    {
        $this->localRepository = $localRepository;
        $this->localDevRepository = $localDevRepository;
        $this->repositoryFactories = $repositoryFactories;

        // initialize repositories
        $repositories = $config->getObject('repositories');

        foreach ($repositories as $name => $repository) {
            $repoType = $repository['type'];
            $this->addRepository($this->createRepository($repoType, $repository));
        }
    }

    /**
     * Searches for a package by it's name and version in managed repositories.
     *
     * @param string $name    package name
     * @param string $version package version
     *
     * @return PackageInterface|null
     */
    public function findPackage($name, $version)
    {
        foreach ($this->repositories as $repository) {
            if ($package = $repository->findPackage($name, $version)) {
                return $package;
            }
        }
    }

    /**
     * Searches for all packages matching a name and optionally a version in managed repositories.
     *
     * @param string $name    package name
     * @param string $version package version
     *
     * @return array
     */
    public function findPackages($name, $version)
    {
        $packages = array();

        foreach ($this->repositories as $repository) {
            $packages = array_merge($packages, $repository->findPackages($name, $version));
        }

        return $packages;
    }

    /**
     * Adds repository
     *
     * @param RepositoryInterface $repository repository instance
     */
    public function addRepository(RepositoryInterface $repository)
    {
        $this->repositories[] = $repository;
    }

    /**
     * Returns a new repository for a specific installation type.
     *
     * @param  string                    $type   repository type
     * @param  string                    $config repository configuration
     * @return RepositoryInterface
     * @throws \InvalidArgumentException if repository for provided type is not registeterd
     */
    public function createRepository($type, $config)
    {
        if (!isset($this->repositoryFactories[$type])) {
            throw new \InvalidArgumentException('Repository type is not registered: '.$type);
        }

        /** @var $factory RepositoryFactoryInterface */
        $factory = $this->repositoryFactories[$type];

        return $factory->createRepository($config);
    }

    /**
     * Returns all repositories, except local one.
     *
     * @return array
     */
    public function getRepositories()
    {
        return $this->repositories;
    }

    /**
     * Returns local repository for the project.
     *
     * @return RepositoryInterface
     */
    public function getLocalRepository()
    {
        return $this->localRepository;
    }

    /**
     * Returns localDev repository for the project.
     *
     * @return RepositoryInterface
     */
    public function getLocalDevRepository()
    {
        return $this->localDevRepository;
    }

    /**
     * Returns all local repositories for the project.
     *
     * @return array[WritableRepositoryInterface]
     */
    public function getLocalRepositories()
    {
        return array($this->localRepository, $this->localDevRepository);
    }
}
