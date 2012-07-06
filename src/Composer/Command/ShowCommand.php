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

namespace Composer\Command;

use Composer\Composer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Robert Schönthal <seroscho@googlemail.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class ShowCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('show')
            ->setDescription('Show information about packages')
            ->setDefinition(array(
                new InputArgument('package', InputArgument::OPTIONAL, 'Package to inspect'),
                new InputArgument('version', InputArgument::OPTIONAL, 'Version to inspect'),
                new InputOption('installed', null, InputOption::VALUE_NONE, 'List installed packages only'),
                new InputOption('platform', null, InputOption::VALUE_NONE, 'List platform packages only'),
                new InputOption('self', null, InputOption::VALUE_NONE, 'Show the root package information'),
            ))
            ->setHelp(<<<EOT
The show command displays detailed information about a package, or
lists all packages available.

EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getApplication()->getContainer();
        $repoLister = $container->getRepoLister();

        if ($input->getArgument('package')) {
            $repoLister->showPackage($input->getOption('platform'), $input->getOption('installed'), $input->getArgument('package'), $input->getArgument('version'));

            return;
        }

        $repoLister->showList($input->getOption('platform'), $input->getOption('installed'));
    }
}
