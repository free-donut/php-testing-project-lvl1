<?php

namespace Downloader\Downloader;

use Downloader\Downloader;
use Monolog\Logger;

function downloadPage(string $url, string $output, string $clientClass): string
{
    $client = new $clientClass();
    $logger = new Logger('LOGGER');
    return Downloader::downloadPage($url, $output, $client, $logger);
}
