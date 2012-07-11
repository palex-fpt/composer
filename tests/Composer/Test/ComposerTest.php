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

namespace Composer\Test;

use Composer\Composer;

class ComposerTest extends TestCase
{
    public function testGetters()
    {
        $repositoryManager = $this->getMockBuilder('Composer\Repository\RepositoryManager')->disableOriginalConstructor()->getMock();
        $repositoryManager->expects($this->any())->method('getLocalRepositories')->will($this->returnValue(array()));
        $composer = new Composer(
            $config = $this->getMock('Composer\Config'),
            $rootPackage = $this->getMock('Composer\Package\PackageInterface'),
            $locker = $this->getMockBuilder('Composer\Package\Locker')->disableOriginalConstructor()->getMock(),
            $repositoryManager,
            $downloadManager = $this->getMock('Composer\Downloader\DownloadManager'),
            $installationManager = $this->getMock('Composer\Installer\InstallationManager'),
            $remoteFilesystem = $this->getMock('Composer\Util\RemoteFilesystem')
        );
        $this->assertSame($config, $composer->getConfig());
        $this->assertSame($rootPackage, $composer->getPackage());
        $this->assertSame($locker, $composer->getLocker());
        $this->assertSame($repositoryManager, $composer->getRepositoryManager());
        $this->assertSame($downloadManager, $composer->getDownloadManager());
        $this->assertSame($installationManager, $composer->getInstallationManager());
        $this->assertSame($remoteFilesystem, $composer->getRemoteFilesystem());
    }
}
