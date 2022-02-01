<?php

namespace Downloader;

use DiDom\Document;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Downloader
{
    private $logger;
    private const TAGS = ['img', 'link', 'script'];

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function downloadPage($url, $path, $client): string
    {
        if (!is_dir($path)) {
            throw new \Exception(sprintf('Directory "%s" not found', $path), 1);
        }

        $pathToLog = "${path}/info.log";
        $this->logger->pushHandler(new StreamHandler($pathToLog, Logger::DEBUG));
        $this->logger->info('Launching page loader', ['url' => $url, 'path' => $path]);

        //$content = $client->get($url)->getBody()->getContents();
        $response = $client->get($url);

        $content = $response->getBody()->getContents();
        if (!$content) {
            $this->logger->notice('Page is empty', ['url' => $url]);
        }
        $document = new Document($content);

        $resources = $this->findResources($document);

        if (count($resources)) {
            $this->logger->info('Page resources found', ['count' => count($resources)]);
            $localResorces = $this->filterLocalResources($resources, $url);
            if (count($localResorces)) {
                $this->logger->info('Local resources found', ['count' => count($localResorces)]);
                $this->saveResources($localResorces, $client, $url, $path);
                $this->replaceRecorcesPath($localResorces, $url);
            } else {
                $this->logger->info('Local resources not found');
            }
        } else {
            $this->logger->info('Page resources not found');
        }

        $formattedContent = $document->format()->html();
        $pageFileName = $this->getPageFileName($url);
        $fullFilePath = "{$path}/{$pageFileName}";
        if (!file_put_contents($fullFilePath, $formattedContent)) {
            $this->logger->critical('Page data is not saved', ['filePath' => $fullFilePath]);
            throw new \Exception(sprintf('Page data is not saved, file path: "%s"', $fullFilePath), 1);
        }
        $this->logger->info('Page downloaded', ['path' => $fullFilePath]);
        return $fullFilePath;
    }

    private function getPageFileName(string $url): string
    {
        $formattedHost = preg_replace('/\W/', '-', parse_url($url, PHP_URL_HOST));
        $formattedPath = preg_replace('/\W/', '-', parse_url($url, PHP_URL_PATH));
        return "{$formattedHost}{$formattedPath}.html";
    }

    private function createDir(string $pathToFiles): void
    {
        if (!is_dir($pathToFiles)) {
            if (mkdir($pathToFiles, 0777, true)) {
                $this->logger->info('Directory created', ['path' => $pathToFiles]);
            } else {
                $this->logger->critical('Directory was not created', ['path' => $pathToFiles]);
                throw new \Exception(sprintf('Directory was not created, path "%s"', $pathToFiles), 1);
            }
        }
    }

    private function getFilesDirectoryName(string $url): string
    {
        $formattedHost = preg_replace('/\W/', '-', parse_url($url, PHP_URL_HOST));
        $formattedPath = preg_replace('/\W/', '-', parse_url($url, PHP_URL_PATH));
        return "{$formattedHost}{$formattedPath}_files";
    }

    private function getResourceFilePath(string $resourceUrl, string $url, string $filesDirectory): string
    {
        $resourcePath = filter_var($resourceUrl, FILTER_VALIDATE_URL) ? parse_url($resourceUrl, PHP_URL_PATH) : $resourceUrl;
        if ($resourcePath === parse_url($url, PHP_URL_PATH)) {
            $pageFileName = $this->getPageFileName($url);
            return "{$filesDirectory}/{$pageFileName}";
        }
        $formattedHost = preg_replace('/\W/', '-', parse_url($url, PHP_URL_HOST));
        $formattedPath = str_replace('/', '-', $resourcePath);
        return "{$filesDirectory}/{$formattedHost}{$formattedPath}";
    }

    private function findResources(Document $document): array
    {
        return array_reduce(self::TAGS, function ($acc, $tag) use ($document) {
            return array_merge($acc, $document->find($tag));
        }, []);
    }

    private function filterLocalResources(array $resources, string $url): array
    {
        return array_values(array_filter($resources, function ($resource) use ($url) {
            $resourceUrl = $resource->href ?? $resource->src;
            if (!$resourceUrl) {
                return false;
            }
            if (filter_var($resourceUrl, FILTER_VALIDATE_URL) === false) {
                return (strpos($resourceUrl, '//') === false);
            }
            return parse_url($resourceUrl, PHP_URL_HOST) === parse_url($url, PHP_URL_HOST);
        }));
    }

    private function saveResources(array $resources, $client, string $url, string $path): void
    {
        $filesDirectory = $this->getFilesDirectoryName($url);
        $pathToFiles = "{$path}/{$filesDirectory}";
        foreach ($resources as $resource) {
            $this->createDir($pathToFiles);
            $resourceUrl = $resource->href ?? $resource->src;
            $resourceFilePath = "{$path}/" . $this->getResourceFilePath($resourceUrl, $url, $filesDirectory);
            $resourceLoggerData = ['tag' => $resource->tag, 'resourceUrl' => $resourceUrl, 'filePath' => $resourceFilePath];
            $this->logger->debug('Attempt to save resource', $resourceLoggerData);
            $response = $client->request('GET', $resourceUrl, ['sink' => $resourceFilePath]);
            if (200 !== $code = $response->getStatusCode()) {
                $this->logger->critical('Uncorrected HTTP response status code when saving a resource', ['code' => $code, 'resourceUrl' => $resourceUrl, 'filePath' => $resourceFilePath]);
                throw new \Exception(sprintf('HTTP response status code when saving a resource for url "%s" is "%s". Expected code is 200', $resourceUrl, $code), 1);
            }
            $this->logger->info('Resource data saved', $resourceLoggerData);
        }
    }

    private function replaceRecorcesPath(array $resources, string $url): void
    {
        $filesDirectory = $this->getFilesDirectoryName($url);
        foreach ($resources as $resource) {
            if ($resource->href) {
                $resource->href = $this->getResourceFilePath($resource->href, $url, $filesDirectory);
                $this->logger->info("Resource path replaced", ['tag' => $resource->tag, 'newPath' => $resource->href]);
            } elseif ($resource->src) {
                $resource->src = $this->getResourceFilePath($resource->src, $url, $filesDirectory);
                $this->logger->info("Resource path replaced", ['tag' => $resource->tag, 'newPath' => $resource->src]);
            } else {
                $this->logger->error("Uncorrected tag atrribute. Resource path is not replaced", ['tag' => $resource->tag]);
            }
        }
    }
}
