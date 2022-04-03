<?php

namespace Downloader;

use DiDom\Document;
use DiDom\Element;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use GuzzleHttp\Client;

class Downloader
{
    /**
     * @var Logger
     */
    private $logger;
    /**
     * @var string
     */
    private $url;
    /**
     * @var string
     */
    private $host;
    /**
     * @var string
     */
    private $path;
    /**
     * @var string
     */
    private $filePath;
    /**
     * @var Client
     */
    private $client;
    /**
     * @var string
     */
    private $pageFileName;
    /**
     * @var string
     */
    private $filesDirectory;
    private const TAGS = ['img', 'link', 'script'];

    public function __construct(string $url, string $filePath, Client $client, Logger $logger)
    {
        $this->logger = $logger;
        $this->url = $url;
        $this->filePath = $filePath;
        $this->client = $client;
        $this->setParams();
    }

    private function setParams(): void
    {
        if (!is_dir($this->filePath)) {
            throw new \Exception(sprintf('Directory "%s" not found', $this->filePath), 1);
        }

        $this->logger->pushHandler(new StreamHandler($this->filePath . '/info.log', Logger::DEBUG));
        $this->logger->info('Launching page loader', ['url' => $this->url, 'path' => $this->filePath]);

        $host = parse_url($this->url, PHP_URL_HOST);
        $path = parse_url($this->url, PHP_URL_PATH);
        if (
            !is_string($host) ||
            !is_string($path)
        ) {
            $this->logger->critical('Uncorected url', ['url' => $this->url]);
            throw new \Exception(sprintf('Uncorected url: "%s"', $this->url), 1);
        }
        $this->host = $host;
        $this->path = $path;
        
        $formattedHost = preg_replace('/\W/', '-', $this->host);
        $formattedPath = preg_replace('/\W/', '-', $this->path);

        $this->pageFileName = "{$formattedHost}{$formattedPath}.html";
        $this->filesDirectory = "{$formattedHost}{$formattedPath}_files";
    }

    public static function downloadPage(string $url, string $path, Client $client, Logger $logger): string
    {
        $downloader = new self($url, $path, $client, $logger);
        $downloader->setParams();
        return $downloader->download();
    }

    public function download(): string
    {
        //$content = $client->get($url)->getBody()->getContents();
        $response = $this->client->get($this->url);

        $content = $response->getBody()->getContents();
        if (!$content) {
            $this->logger->notice('Page is empty', ['url' => $this->url]);
        }
        $document = new Document($content);

        $resources = $this->findResources($document);

        if (count($resources) > 0) {
            $this->logger->info('Page resources found', ['count' => count($resources)]);
            $localResorces = $this->filterLocalResources($resources);
            if (count($localResorces) > 0) {
                $this->logger->info('Local resources found', ['count' => count($localResorces)]);
                $this->saveResources($localResorces);
                $this->replaceRecorcesPath($localResorces);
            } else {
                $this->logger->info('Local resources not found');
            }
        } else {
            $this->logger->info('Page resources not found');
        }

        $formattedContent = $document->format()->html();
        $fullFilePath = $this->filePath . '/' . $this->pageFileName;
        if (!file_put_contents($fullFilePath, $formattedContent)) {
            $this->logger->critical('Page data is not saved', ['filePath' => $fullFilePath]);
            throw new \Exception(sprintf('Page data is not saved, file path: "%s"', $fullFilePath), 1);
        }
        $this->logger->info('Page downloaded', ['path' => $fullFilePath]);
        return $fullFilePath;
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

    private function getResourceFilePath(string $resourceUrl): string
    {
        $resourcePath = filter_var($resourceUrl, FILTER_VALIDATE_URL) ?
            parse_url($resourceUrl, PHP_URL_PATH) :
            $resourceUrl;
        if (!is_string($resourcePath)) {
            $this->logger->critical('Uncorrected resource path', ['resourceUrl' => $resourceUrl]);
            throw new \Exception(sprintf('Uncorrected resource Url "%s"', $resourceUrl), 1);
        }

        if ($resourcePath === $this->path) {// для главной страницы
            return $this->filesDirectory . '/' . $this->pageFileName;
        }
        $formattedHost = preg_replace('/\W/', '-', $this->host);
        $formattedPath = str_replace('/', '-', $resourcePath);
        return $this->filesDirectory . "/{$formattedHost}{$formattedPath}";
    }

    /**
     * @return Element[]
     */
    private function findResources(Document $document): array
    {
        return array_reduce(self::TAGS, function ($acc, $tag) use ($document) {
            return array_merge($acc, $document->find($tag));
        }, []);
    }

    /**
     * @param Element[] $resources
     * @return Element[]
     */
    private function filterLocalResources(array $resources): array
    {
        $host = $this->host;
        return array_values(array_filter($resources, function ($resource) use ($host) {
            $resourceUrl = $resource->href ?? $resource->src;
            if (!$resourceUrl) {
                return false;
            }
            if (filter_var($resourceUrl, FILTER_VALIDATE_URL) === false) {
                return (strpos($resourceUrl, '//') === false);
            }
            return parse_url($resourceUrl, PHP_URL_HOST) === $host;
        }));
    }

    /**
     * @param Element[] $resources
     */
    private function saveResources(array $resources): void
    {
        $pathToFiles = $this->filePath . '/' . $this->filesDirectory;
        foreach ($resources as $resource) {
            $this->createDir($pathToFiles);
            $resourceUri = $resource->href ?? $resource->src;
            //$resourceUrl = $url . str_replace($url, '', $resourceUri);//url может содержать путь, исправить ошибку Client error: `GET https://ru.hexlet.io/courseshttps://ru.hexlet.io/lessons.rss` resulted in a `404 Not Found` 
            $resourceUrl = filter_var($resourceUri, FILTER_VALIDATE_URL) ?
                $resourceUri :
                $this->url . $resourceUri;
            $resourceFilePath = $this->filePath . '/' . $this->getResourceFilePath($resourceUri);
            $resourceLoggerData = [
                'tag' => $resource->tag,
                'resourceUrl' => $resourceUri,
                'filePath' => $resourceFilePath
            ];
            $this->logger->debug('Attempt to save resource', $resourceLoggerData);
            $response = $this->client->request('GET', $resourceUrl, ['sink' => $resourceFilePath]);
            if (200 !== $code = $response->getStatusCode()) {
                $this->logger->critical(
                    'Uncorrected HTTP response status code when saving a resource',
                    ['code' => $code, 'resourceUrl' => $resourceUrl, 'filePath' => $resourceFilePath]
                );
                $message = sprintf('HTTPstatus code for resource "%s" is "%s". 200 expected', $resourceUri, $code);
                throw new \Exception($message, 1);
            }
            $this->logger->info('Resource data saved', $resourceLoggerData);
        }
    }

    /**
     * @param Element[] $resources
     */
    private function replaceRecorcesPath(array $resources): void
    {
        foreach ($resources as $resource) {
            if (isset($resource->href)) {
                $resource->href = $this->getResourceFilePath($resource->href);
                $this->logger->info("Resource path replaced", ['tag' => $resource->tag, 'newPath' => $resource->href]);
            } elseif (isset($resource->src)) {
                $resource->src = $this->getResourceFilePath($resource->src);
                $this->logger->info("Resource path replaced", ['tag' => $resource->tag, 'newPath' => $resource->src]);
            } else {
                $this->logger->error("Uncorrected tag atrribute. Path is not replaced", ['tag' => $resource->tag]);
            }
        }
    }
}
