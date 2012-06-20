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

namespace Composer\Test\Mock;

use Composer\Config;
use Composer\Factory;
use Composer\Repository;
use Composer\Repository\RepositoryManager;
use Composer\Installer;
use Composer\Downloader;
use Composer\IO\IOInterface;

class FactoryMock extends Factory
{
    public static function createConfig()
    {
        $config = new Config();

        $tempDir = sys_get_temp_dir();
        $localConfig = json_decode(<<<JSON
{ "config": {
    "home": "$tempDir/composer-test",
    "default-repositories": {
        "packagist": false
    }
}}
JSON
, true);


        $config->merge($localConfig);

        return $config;
    }

    protected function addLocalRepository(RepositoryManager $rm, $vendorDir)
    {
        $rm->setLocalRepository(new WritableRepositoryMock());
        $rm->setLocalDevRepository(new WritableRepositoryMock());
    }

    protected function addPackagistRepository(array $localConfig)
    {
        return $localConfig;
    }

    protected function createInstallationManager()
    {
        return new InstallationManagerMock;
    }

    protected function purgePackages(Repository\RepositoryManager $rm, Installer\InstallationManager $im)
    {
    }
}
