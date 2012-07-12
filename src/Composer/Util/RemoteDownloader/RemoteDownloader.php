<?php

namespace Composer\Util\RemoteDownloader;

/**
 * Created by JetBrains PhpStorm.
 * User: palex
 * Date: 12.07.12
 * Time: 13:26
 * To change this template use File | Settings | File Templates.
 */
use Composer\Downloader\TransportException;


class RemoteDownloader implements RemoteDownloaderInterface
{
    private $driver;

    public function __construct()
    {
        $this->driver = new CurlDriver();
    }

    public function downloadResource($uri, DownloadProgressNotifierInterface $notifier = null)
    {
        $result = $this->driver->download(array($uri), $notifier);
        if (empty($result[$uri])) {
            throw new TransportException(sprintf('failed to download `%s`', $uri));
        };

        return $result[$uri];
    }

    public function downloadResources(array $uris, DownloadProgressNotifierInterface $notifier = null)
    {
        $result = $this->driver->download($uris, $notifier);

        $failedUris = array();
        foreach ($result as $uri => $content) {
            if (empty($content)) {
                $failedUris[] = $uri;
            }
        };
        if (count($failedUris)) {
            throw new TransportException(sprintf('failed to download `%s`', implode(', ', $failedUris)));
        }

        return $result;
    }
}

class CurlDriver
{
    public function download(array $uris, DownloadProgressNotifierInterface $notifier = null)
    {
        $result = array();

        $notifier = new CurlAggregateAdapter($notifier);

        $channels = array();
        $master = curl_multi_init();
        foreach ($uris as $uri) {
            $channel = $this->configureChannel($uri, $notifier);
            curl_multi_add_handle($master, $channel);
            $channels[$uri] = $channel;
        }

        // start transfer
        $still_running = true;
        while ($still_running) {
            curl_multi_exec($master, $still_running);
        }

        // parse results
        foreach ($channels as $uri => $channel) {
            $content = curl_multi_getcontent($channel);
            $code = curl_getinfo($channel, CURLINFO_HTTP_CODE);
            $fileTime = curl_getinfo($channel, CURLINFO_FILETIME);
            $result[$uri] = $content;
            curl_multi_remove_handle($master, $channel);
            curl_close($channel);
        }

        curl_multi_close($master);

        return $result;
    }

    /**
     * Returns configured curl channel
     *
     * @param $uri
     */
    private function configureChannel($uri, $notifier)
    {
        $notifyAdapter = new CurlNotifyAdapter($uri, $notifier);

        $channel = curl_init($uri);
        curl_setopt_array($channel, array(
            CURL_HTTP_VERSION_1_1 => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_AUTOREFERER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_DNS_USE_GLOBAL_CACHE => true,
            CURLOPT_FILETIME => true,
            CURLOPT_PROGRESSFUNCTION => array($notifyAdapter, 'curlCallback'),
            CURLOPT_URL => $uri,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_HEADER => false,
            CURLOPT_NETRC => true,
            CURLOPT_NOPROGRESS => false,
        ));

        return $channel;
    }
}

class CurlNotifyAdapter
{
    public function __construct($uri, CurlAggregateAdapter $notifier = null)
    {
        $this->uri = $uri;
        $this->notifier = $notifier;
    }

    public function curlCallback($download_size, $downloaded, $upload_size, $uploaded)
    {
        if (!empty($this->notifier)) {
            $this->notifier->OnProgress($this->uri, $downloaded, $download_size);
        }
    }
}

class CurlAggregateAdapter
{
    private $uris = array();

    public function __construct(DownloadProgressNotifierInterface $notifier = null)
    {
        $this->notifier = $notifier;
    }

    function OnProgress($uri, $downloaded, $total)
    {
        if (!empty($this->notifier)) {
            if (!isset($this->uris[$uri])) {
                $this->notifier->OnStarted($uri);
            }
            $this->notifier->OnProgress($uri, $downloaded, $total);
            if ($downloaded === $total) {
                $this->notifier->OnCompleted($uri);
            }
        }
        $this->uris[$uri] = array('downloaded' => $downloaded, 'total' => $total);
    }
}

class IONotifier implements DownloadProgressNotifierInterface
{
    private $uris = array();
    private $lastProgression = 0;
    public function __construct(\Composer\IO\IOInterface $io)
    {
        $this->io = $io;
        $this->lastProgress = 0;
    }

    function OnProgress($uri, $downloaded, $total)
    {
        $this->uris[$uri] = array($downloaded, $total);
        $totalDownloaded = array_reduce($this->uris, function ($result, $item) { return $result + $item[0]; }, 0);
        $totalSize = array_reduce($this->uris, function ($result, $item) { return $result + $item[1]; }, 0);

        $progression = 0;
        if ($totalSize > 0) {
            $progression = $totalDownloaded * 100 / $totalSize;
        }
        if ((0 === $progression % 5) && $progression !== $this->lastProgress) {
            $this->lastProgress = $progression;
            $this->io->overwrite("    Downloading: <comment>$progression%</comment>", false);
        }
    }

    function OnStarted($uri)
    {
        // TODO: Implement OnStarted() method.
    }

    function OnCompleted($uri)
    {
        $totalDownloaded = array_reduce($this->uris, function ($result, $item) { return $result + $item[0]; }, 0);
        $totalSize = array_reduce($this->uris, function ($result, $item) { return $result + $item[1]; }, 0);
        if ($totalDownloaded == $totalSize) {
            $this->io->overwrite("    Downloading: <comment>100%</comment>");
        }
    }


}