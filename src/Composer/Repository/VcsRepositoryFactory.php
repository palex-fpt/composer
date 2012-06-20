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
use Composer\IO\IOInterface;

/**
 * @author Alexey Prilipko <palex@farpost.com>
 */
class VcsRepositoryFactory implements RepositoryFactoryInterface
{
    private $config;
    private $io;
    private $drivers;

    public function __construct(Config $config, IOInterface $io, $drivers = null)
    {
        $this->config = $config;
        $this->io = $io;
        $this->drivers = $drivers;
    }

    public function createRepository($config) {
        return new VcsRepository($config, $this->io, $this->config, $this->drivers);
    }
}
