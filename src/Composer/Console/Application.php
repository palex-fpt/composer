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

namespace Composer\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Composer\Command;
use Composer\Command\Helper\DialogHelper;
use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\IO\ConsoleIO;
use Composer\Util\ErrorHandler;

/**
 * The console application that handles the commands
 *
 * @author Ryan Weaver <ryan@knplabs.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author François Pluchino <francois.pluchino@opendisplay.com>
 */
class Application extends BaseApplication
{
    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @var IOInterface
     */
    protected $io;

    public function __construct()
    {
        ErrorHandler::register();
        parent::__construct('Composer', Composer::VERSION);
    }

    /**
     * {@inheritDoc}
     */
    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        if (null === $output) {
            $styles['highlight'] = new OutputFormatterStyle('red');
            $styles['warning'] = new OutputFormatterStyle('black', 'yellow');
            $formatter = new OutputFormatter(null, $styles);
            $output = new ConsoleOutput(ConsoleOutput::VERBOSITY_NORMAL, null, $formatter);
        }

        return parent::run($input, $output);
    }

    /**
     * {@inheritDoc}
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->io = new ConsoleIO($input, $output, $this->getHelperSet());

        if (version_compare(PHP_VERSION, '5.3.2', '<')) {
            $output->writeln('<warning>Composer only officially supports PHP 5.3.2 and above, you will most likely encounter problems with your PHP '.PHP_VERSION.', upgrading is strongly recommended.</warning>');
        }

        if (defined('COMPOSER_DEV_WARNING_TIME') && $this->getCommandName($input) !== 'self-update') {
            if (time() > COMPOSER_DEV_WARNING_TIME) {
                $output->writeln(sprintf('<warning>This dev build of composer is outdated, please run "%s self-update" to get the latest version.</warning>', $_SERVER['PHP_SELF']));
            }
        }

        return parent::doRun($input, $output);
    }

    private function getConfig()
    {
        $configFactory = new \Composer\ConfigFactory();
        $config = $configFactory->createConfig();
        return $config;
    }

    /**
     * @param  bool               $required
     * @return \Composer\Composer
     */
    public function getComposer($required = true)
    {
        if (null === $this->composer) {
            try {
                $container = new \Composer\DependencyContainer($this->getConfig()->getArray());
                $container->setParameter('io', $this->getIO());
                $this->composer = $container->getInstance('composer');
            } catch (\InvalidArgumentException $e) {
                if (1 || $required) {
                    $this->io->write($e->getMessage());
                    exit(1);
                }
            }
        }

        return $this->composer;
    }

    public function getContainer($required = true)
    {
        $container = new \Composer\DependencyContainer($this->getConfig()->getArray());
        $container->setParameter('io', $this->getIO());
        return $container;
    }

    /**
     * @return IOInterface
     */
    public function getIO()
    {
        return $this->io;
    }

    /**
     * Initializes all the composer commands
     */
    protected function getDefaultCommands()
    {
        $container = new \Composer\DependencyContainer($this->getConfig()->getArray());
        $container->setParameter('io', $this->getIO());

        $commands = parent::getDefaultCommands();
        $commands[] = new Command\AboutCommand();
        $commands[] = new Command\DependsCommand();
        $commands[] = new Command\InitCommand();
        $commands[] = new Command\InstallCommand();
        $commands[] = new Command\CreateProjectCommand();
        $commands[] = new Command\UpdateCommand();
        $commands[] = new Command\SearchCommand();
        $commands[] = new Command\ValidateCommand();
        $commands[] = new Command\ShowCommand();
        $commands[] = new Command\RequireCommand();

        if ('phar:' === substr(__FILE__, 0, 5)) {
            $commands[] = new Command\SelfUpdateCommand();
        }

        return $commands;
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultHelperSet()
    {
        $helperSet = parent::getDefaultHelperSet();

        $helperSet->set(new DialogHelper());

        return $helperSet;
    }
}
