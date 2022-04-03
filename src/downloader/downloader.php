<?php

namespace Downloader\Downloader;

use Downloader\Downloader;
use Monolog\Logger;
use GuzzleHttp\Exception\ConnectException;

function downloadPage(string $url, string $output, string $clientClass): string
{
    $client = new $clientClass();
    $logger = new Logger('LOGGER');
    $formattedUrl = trim($url, '/');
    $pageLoader = new Downloader($logger);
    return $pageLoader->downloadPage($formattedUrl, $output, $client);
}
