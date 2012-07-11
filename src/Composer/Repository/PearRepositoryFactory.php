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
use Composer\Util\RemoteFilesystem;
use Composer\IO\IOInterface;

/**
 * @author Alexey Prilipko <palex@farpost.com>
 */
class PearRepositoryFactory implements RepositoryFactoryInterface
{
    private $io;
    private $rfs;

    public function __construct(IOInterface $io, RemoteFilesystem $rfs = null)
    {
        $this->io = $io;
        $this->rfs = $rfs;
    }

    public function createRepository(array $config)
    {
        return new PearRepository($config, $this->io, $this->rfs);
    }
}
