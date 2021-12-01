<?php

namespace Hexlet\Code;

use DiDom\Document;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class PageLoader
{
    private $logger;
    private const RESOURCES_MAP = [
            ['tag' => 'img', 'attribute' => 'src'],
            ['tag' => 'link', 'attribute' => 'href'],
            ['tag' => 'script', 'attribute' => 'src']
        ];

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function downloadPage($url, $path, $client): string
    {
        if (!is_dir($path = $path)) {
            throw new \Exception(sprintf('Directory "%s" not found', $path), 1);
        }
        $this->setLogger($path);
        $this->logger->info('Launching page loader', ['url' => $url, 'path' => $path]);


        //$content = $client->get($url)->getBody()->getContents();
        $response = $client->get($url);
        if (200 !== $code = $response->getStatusCode()) {
            $this->logger->error('Uncorrected status code', ['url' => $url, 'code' => $code]);
            throw new \Exception(sprintf('Status code is "%s"', $code), 1);
        }
        $content = $response->getBody()->getContents();
        $document = new Document($content);
        $this->downloadResources($client, $url, $path, $document);

        $formattedContent = $document->format()->html();
        $pageFileName = $this->getPageFileName($url);
        $fullFilePath = "{$path}/{$pageFileName}";
        file_put_contents($fullFilePath, $formattedContent);
        $this->logger->info('Page downloaded', ['path' => $fullFilePath]);
        return $fullFilePath;
    }

    private function setLogger(string $path): void
    {
        $pathToLog = "${path}/info.log";
        $this->logger->pushHandler(new StreamHandler($pathToLog, Logger::INFO));
    }

    private function getPageFileName(string $url): string
    {
        $formattedHost = preg_replace('/\W/', '-', parse_url($url, PHP_URL_HOST));
        $formattedPath = preg_replace('/\W/', '-', parse_url($url, PHP_URL_PATH));
        return "{$formattedHost}{$formattedPath}.html";
    }

    private function createDir(string $pathToFiles): self
    {
        if (!is_dir($pathToFiles)) {
            if(mkdir($pathToFiles, 0777, true)) {
                $this->logger->info('Directory created', ['path' => $pathToFiles]);
            } else {
                $this->logger->error('Directory was not created', ['path' => $pathToFiles]);
            }
        }
        return $this;
    }

    private function getFilesDirectoryName(string $url): string
    {
        $formattedHost = preg_replace('/\W/', '-', parse_url($url, PHP_URL_HOST));
        $formattedPath = preg_replace('/\W/', '-', parse_url($url, PHP_URL_PATH));
        return "{$formattedHost}{$formattedPath}_files";
    }

    private function getResourceLink(string $resourceUrl, string $url, string $filesDirectory): string
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

    private function isLocalResource(string $resourceUrl, string $url): bool
    {
        if (filter_var($resourceUrl, FILTER_VALIDATE_URL) === false) {
            return true;
        }
        return parse_url($resourceUrl, PHP_URL_HOST) === parse_url($url, PHP_URL_HOST);
    }

    private function downloadResources($client, string $url, string $path, Document $document): self
    {
        $filesDirectory = $this->getFilesDirectoryName($url);
        $pathToFiles = "{$path}/{$filesDirectory}";
        foreach (self::RESOURCES_MAP as $resourceData) {
            ['tag' => $tag, 'attribute' => $attribute] = $resourceData;
            $resources = $document->find($tag);

            foreach ($resources as $key => $resource) {
                $resourceUrl = $resource->$attribute;
                if ($resourceUrl && $this->isLocalResource($resourceUrl, $url)) {
                    $this->createDir($pathToFiles);
                    $newResourceLink = $this->getResourceLink($resourceUrl, $url, $filesDirectory);
                    $resourcePath = "{$path}/{$newResourceLink}";
                    $client->request('GET', $resourceUrl, ['sink' => $resourcePath]);
                    $document->find($tag)[$key]->$attribute = $newResourceLink;
                    $this->logger->info(ucfirst("{$tag} data downloaded"), ['url' => $resourceUrl, 'path' => $resourcePath]);
                }
            }
        }
        return $this;
    }
}
