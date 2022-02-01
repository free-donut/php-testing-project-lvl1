<?php

namespace Downloader\Downloader;

use Downloader\Downloader;
use Monolog\Logger;

function downloadPage($url, $output, $clientClass)
{
    $client = new $clientClass();
    $logger = new Logger('LOGGER');
    try {
        $pageLoader = new Downloader($logger);
        $path = $pageLoader->downloadPage($url, $output, $client);
        print_r('Page was successfully downloaded into ');
        print_r($path);
        print_r(PHP_EOL);
    } catch (\Exception $e) {
        fwrite(STDERR, sprintf('Application terminated with an error: "%s"%s', $e->getMessage(), PHP_EOL));
        exit($e->getCode());
    }
}
