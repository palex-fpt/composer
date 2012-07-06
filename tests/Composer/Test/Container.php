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

namespace Composer\Test;

use Composer\Container as DefaultContainer;
use Composer\Test\Mock\InstalledFilesystemRepositoryMock;
use Composer\Test\Mock\InstallationManagerMock;

/**
 * Dependency container
 *
 * @author Alexey Prilipko <palex@farpost.com>
 */
class Container extends DefaultContainer
{
    protected function getLockerLockFile()
    {
        return $this->getParameter('lockJsonMock');
    }

    protected function getInstallationManager()
    {
        return isset($this->singletons[__METHOD__]) ? $this->singletons[__METHOD__] : $this->singletons[__METHOD__] =
            new InstallationManagerMock(
            $this->getInstallers()
        );
    }

    protected function getLocalRepository()
    {
        return isset($this->singletons[__METHOD__]) ? $this->singletons[__METHOD__] : $this->singletons[__METHOD__] =
            new InstalledFilesystemRepositoryMock(
            $this->getParameter('installed')
        );
    }

    protected function getLocalDevRepository()
    {
        return isset($this->singletons[__METHOD__]) ? $this->singletons[__METHOD__] : $this->singletons[__METHOD__] =
            new InstalledFilesystemRepositoryMock(
            $this->getParameter('installedDev')
        );
    }

    protected function getAutoloadGenerator()
    {
        return $this->getParameter('autoloadGenerator');
    }
}
