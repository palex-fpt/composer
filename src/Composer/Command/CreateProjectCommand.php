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

use Composer\Installer;
use Composer\Installer\ProjectInstaller;
use Composer\IO\IOInterface;
use Composer\Repository\ComposerRepository;
use Composer\Repository\CompositeRepository;
use Composer\Repository\FilesystemRepository;
use Composer\Repository\NotifiableRepositoryInterface;
use Composer\Repository\InstalledFilesystemRepository;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Composer\Json\JsonFile;
use Composer\Util\RemoteFilesystem;
use Composer\Package\Version\VersionParser;

/**
 * Install a package as new project into new directory.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class CreateProjectCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('create-project')
            ->setDescription('Create new project from a package into given directory.')
            ->setDefinition(array(
                new InputArgument('package', InputArgument::REQUIRED, 'Package name to be installed'),
                new InputArgument('directory', InputArgument::OPTIONAL, 'Directory where the files should be created'),
                new InputArgument('version', InputArgument::OPTIONAL, 'Version, will defaults to latest'),
                new InputOption('prefer-source', null, InputOption::VALUE_NONE, 'Forces installation from package sources when possible, including VCS information.'),
                new InputOption('repository-url', null, InputOption::VALUE_REQUIRED, 'Pick a different repository url to look for the package.'),
                new InputOption('dev', null, InputOption::VALUE_NONE, 'Whether to install dependencies for development.')
            ))
            ->setHelp(<<<EOT
The <info>create-project</info> command creates a new project from a given
package into a new directory. You can use this command to bootstrap new
projects or setup a clean version-controlled installation
for developers of your project.

<info>php composer.phar create-project vendor/project target-directory [version]</info>

To setup a developer workable version you should create the project using the source
controlled code by appending the <info>'--prefer-source'</info> flag. Also, it is
advisable to install all dependencies required for development by appending the
<info>'--dev'</info> flag.

To install a package from another repository repository than the default one you
can pass the <info>'--repository-url=http://myrepository.org'</info> flag.

EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        return $this->installProject(
            $this->getIO(),
            $input->getArgument('package'),
            $input->getArgument('directory'),
            $input->getArgument('version'),
            $input->getOption('prefer-source'),
            $input->getOption('dev'),
            $input->getOption('repository-url')
        );
    }

    public function installProject(IOInterface $io, $packageName, $directory = null, $version = null, $preferSource = false, $installDevPackages = false, $repositoryUrl = null)
    {
        /** @var $composer \Composer\Composer */
        $composer = $this->getApplication()->getComposer();
        $dm = $composer->getDownloadManager();
        if ($preferSource) {
            $dm->setPreferSource(true);
        }

        $config = $composer->getConfig();
        if (null === $repositoryUrl) {
            $sourceRepo = new CompositeRepository($composer->getRepositoryManager()->getRepositories());
        } elseif ("json" === pathinfo($repositoryUrl, PATHINFO_EXTENSION)) {
            $sourceRepo = new FilesystemRepository(new JsonFile($repositoryUrl, new RemoteFilesystem($io)));
        } elseif (0 === strpos($repositoryUrl, 'http')) {
            $sourceRepo = new ComposerRepository(array('url' => $repositoryUrl), $io, $config);
        } else {
            throw new \InvalidArgumentException("Invalid repository url given. Has to be a .json file or an http url.");
        }

        $candidates = $sourceRepo->findPackages($packageName, $version);
        if (!$candidates) {
            throw new \InvalidArgumentException("Could not find package $packageName" . ($version ? " with version $version." : ''));
        }

        if (null === $directory) {
            $parts = explode("/", $packageName, 2);
            $directory = getcwd() . DIRECTORY_SEPARATOR . array_pop($parts);
        }

        // select highest version if we have many
        $package = $candidates[0];
        foreach ($candidates as $candidate) {
            if (version_compare($package->getVersion(), $candidate->getVersion(), '<')) {
                $package = $candidate;
            }
        }

        $io->write('<info>Installing ' . $package->getName() . ' (' . VersionParser::formatVersion($package, false) . ')</info>', true);
        if (0 === strpos($package->getPrettyVersion(), 'dev-') && in_array($package->getSourceType(), array('git', 'hg'))) {
            $package->setSourceReference(substr($package->getPrettyVersion(), 4));
        }

        $projectInstaller = new ProjectInstaller($directory, $dm);
        $projectInstaller->install(new InstalledFilesystemRepository(new JsonFile('php://memory')), $package);
        if ($package->getRepository() instanceof NotifiableRepositoryInterface) {
            $package->getRepository()->notifyInstall($package);
        }

        $io->write('<info>Created project in ' . $directory . '</info>', true);
        chdir($directory);

        putenv('COMPOSER_ROOT_VERSION='.$package->getPrettyVersion());

        $container = $this->getApplication()->getContainer();
        /** @var $shower \Composer\RepoLister */
        $install = $container->getInstance('installer');

        $install
            ->setPreferSource($preferSource)
            ->setDevMode($installDevPackages)
            ->run();
    }
}
