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
class ComposerRepositoryFactory implements RepositoryFactoryInterface
{
    protected $config;
    protected $io;

    public function __construct()
    {
//        $this->config = $config;
//        $this->io = $io;
    }

    public function createRepository($config) {
        return new ComposerRepository($config, $this->io, $this->config);
    }
}
