<?php

namespace Composer\Util\RemoteDownloader;

/**
 * Created by JetBrains PhpStorm.
 * User: palex
 * Date: 12.07.12
 * Time: 13:26
 * To change this template use File | Settings | File Templates.
 */
interface RemoteDownloaderInterface
{
    function downloadResource($uri, DownloadProgressNotifierInterface $notifier = null);

    function downloadResources(array $uri, DownloadProgressNotifierInterface $notifier = null);
}

interface DownloadProgressNotifierInterface
{
    function OnStarted($uri);
    function OnProgress($uri, $downloaded, $total);
    function OnCompleted($uri);
}