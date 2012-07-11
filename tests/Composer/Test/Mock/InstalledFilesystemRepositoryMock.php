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

use Composer\Repository\InstalledFilesystemRepository;
use Composer\Package\Loader\ArrayLoader;

class InstalledFilesystemRepositoryMock extends InstalledFilesystemRepository
{
    private $data;

    public function __construct(array $data = array())
    {
        $this->data = $data;
    }

    protected function initialize()
    {
        $this->packages = array();
        $packages = $this->data;

        $loader = new ArrayLoader();
        foreach ($packages as $packageData) {
            $package = $loader->load($packageData);
            $this->addPackage($package);
        }
    }

    public function reload()
    {
    }

    public function write()
    {
    }
}
