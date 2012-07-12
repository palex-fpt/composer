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

namespace Composer\Repository\Pear;

use Composer\Util\RemoteFilesystem;
use Composer\Util\RemoteDownloader\RemoteDownloaderInterface;

/**
 * Base PEAR Channel reader.
 *
 * Provides xml namespaces and red
 *
 * @author Alexey Prilipko <palex@farpost.com>
 */
abstract class BaseChannelReader
{
    /**
     * PEAR REST Interface namespaces
     */
    const CHANNEL_NS = 'http://pear.php.net/channel-1.0';
    const ALL_CATEGORIES_NS = 'http://pear.php.net/dtd/rest.allcategories';
    const CATEGORY_PACKAGES_INFO_NS = 'http://pear.php.net/dtd/rest.categorypackageinfo';
    const ALL_PACKAGES_NS = 'http://pear.php.net/dtd/rest.allpackages';
    const ALL_RELEASES_NS = 'http://pear.php.net/dtd/rest.allreleases';
    const PACKAGE_INFO_NS = 'http://pear.php.net/dtd/rest.package';

    /** @var RemoteDownloaderInterface */
    private $remoteDownloader;

    protected function __construct(RemoteDownloaderInterface $remoteDownloader)
    {
        $this->remoteDownloader = $remoteDownloader;
    }

    /**
     * Read content from remote filesystem.
     *
     * @param $origin string server
     * @param $path   string relative path to content
     * @return \SimpleXMLElement
     */
    protected function requestContent($origin, $path)
    {
        $url = rtrim($origin, '/') . '/' . ltrim($path, '/');
        $content = $this->remoteDownloader->downloadResource($url, null);
        if (!$content) {
            throw new \UnexpectedValueException('The PEAR channel at ' . $url . ' did not respond.');
        }

        return $content;
    }

    /**
     * Read xml content from remote filesystem
     *
     * @param $origin string server
     * @param $path   string relative path to content
     * @return \SimpleXMLElement
     */
    protected function requestXml($origin, $path)
    {
        // http://components.ez.no/p/packages.xml is malformed. to read it we must ignore parsing errors.
        $xml = simplexml_load_string($this->requestContent($origin, $path), "SimpleXMLElement", LIBXML_NOERROR);

        if (false == $xml) {
            $url = rtrim($origin, '/') . '/' . ltrim($path, '/');
            throw new \UnexpectedValueException(sprintf('The PEAR channel at ' . $origin . ' is broken. (Invalid XML at file `%s`)', $path));
        }

        return $xml;
    }

    protected function requestXmls($origin, array $uris)
    {
        $result = array();
        $contents = $this->remoteDownloader->downloadResources($uris, null);

        foreach ($contents as $uri => $content) {
            // http://components.ez.no/p/packages.xml is malformed. to read it we must ignore parsing errors.
            $xml = simplexml_load_string($content, "SimpleXMLElement", LIBXML_NOERROR);

            if (false == $xml) {
                throw new \UnexpectedValueException(sprintf('The PEAR channel at ' . $origin . ' is broken. (Invalid XML at `%s`)', $uri));
            }

            $result[$uri] = $xml;
        }

        return $result;
    }
}
