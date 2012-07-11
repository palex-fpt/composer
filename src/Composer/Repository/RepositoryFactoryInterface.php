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
 * @author Alexey Prilipko <palex@farpost.com>
 */
interface RepositoryFactoryInterface
{
    /**
     * Creates instance of repository
     *
     * @param  array             $config
     * @return RepositoryFactory
     */
    public function createRepository(array $config);
}
