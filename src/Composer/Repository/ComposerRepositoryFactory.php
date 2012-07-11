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
use Composer\Composer;
use Composer\IO\IOInterface;

/**
 * @author Alexey Prilipko <palex@farpost.com>
 */
class ComposerRepositoryFactory implements RepositoryFactoryInterface
{
    private $io;
    private $config;

    public function __construct(IOInterface $io, Config $config)
    {
        $this->io = $io;
        $this->config = $config;
    }

    public function createRepository(array $config)
    {
        return new ComposerRepository($config, $this->io, $this->config);
    }
}
