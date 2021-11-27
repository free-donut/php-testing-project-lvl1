<?php

namespace Hexlet\Code;

use DiDom\Document;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class PageLoader
{
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function downloadPage($url, $path, $client): string
    {

        if (!is_dir($path)) {
            throw new \Exception("Directory not found ${path}", 1);
        }
        $this->setLogger($path);
        $this->logger->info('Launching page loader', ['url' => $url, 'path' => $path]);

        $content = $client->get($url)->getBody()->getContents();
        $document = new Document($content);
        $filesDirectory = $this->getFilesDirectoryName($url);
        $pathToFiles = "{$path}/{$filesDirectory}";

        $images = $document->find('img');
        foreach ($images as $key => $image) {
            $imageUrl = $image->src;
            if ($this->isLocalResource($imageUrl, $url)) {
                $this->createDir($pathToFiles);
                $newImageLink = $this->getResourceLink($imageUrl, $url, $filesDirectory);
                $document->find('img')[$key]->src = $newImageLink;
                $imagePath = "{$path}/{$newImageLink}";
                $client->request('GET', $imageUrl, ['sink' => $imagePath]);
                $this->logger->info('Imade dowloaded', ['url' => $imageUrl, 'path' => $imagePath]);
            }
        }

        $links = $document->find('link');
        foreach ($links as $key => $link) {
            $linkUrl = $link->href;
            if ($this->isLocalResource($linkUrl, $url)) {
                $this->createDir($pathToFiles);
                $isCanonicalLink = ($link->rel === 'canonical');
                $newScriptLink = $this->getResourceLink($linkUrl, $url, $filesDirectory, $isCanonicalLink);
                $document->find('link')[$key]->href = $newScriptLink;
                $linkPath = "{$path}/{$newScriptLink}";
                $client->request('GET', $linkUrl, ['sink' => $linkPath]);
                $this->logger->info('Link data downloaded', ['url' => $linkUrl, 'path' => $linkPath]);
            }
        }

        $scripts = $document->find('script');
        foreach ($scripts as $key => $script) {
            if (null !== ($scriptUrl = $script->src)) {
                if ($this->isLocalResource($scriptUrl, $url)) {
                    $this->createDir($pathToFiles);
                    $newScriptLink = $this->getResourceLink($scriptUrl, $url, $filesDirectory);
                    $document->find('script')[$key]->src = $newScriptLink;
                    $scriptPath = "{$path}/{$newScriptLink}";
                    $client->request('GET', $scriptUrl, ['sink' => $scriptPath]);
                    $this->logger->info('Script data downloaded', ['url' => $scriptUrl, 'path' => $scriptPath]);
                }
            }
        }

        $content = $document->format()->html();
        $pageFileName = $this->getPageFileName($url);
        $fullFilePath = "{$path}/{$pageFileName}";
        file_put_contents($fullFilePath, $content);
        $this->logger->info('Page downloaded', ['path' => $fullFilePath]);
        return $fullFilePath;
    }

    private function setLogger($path)
    {
        $pathToLog = "${path}/info.log";
        $this->logger->pushHandler(new StreamHandler($pathToLog, Logger::INFO));
    }

    private function getPageFileName($url): string
    {
        $formattedHost = preg_replace('/\W/', '-', parse_url($url, PHP_URL_HOST));
        $formattedPath = preg_replace('/\W/', '-', parse_url($url, PHP_URL_PATH));
        return "{$formattedHost}{$formattedPath}.html";
    }

    private function createDir($path): self
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
            $this->logger->info('Directory created', ['path' => $path]);
        }
        return $this;
    }

    private function getFilesDirectoryName(string $url): string
    {
        $formattedHost = preg_replace('/\W/', '-', parse_url($url, PHP_URL_HOST));
        $formattedPath = preg_replace('/\W/', '-', parse_url($url, PHP_URL_PATH));
        return "{$formattedHost}{$formattedPath}_files";
    }

    private function getResourceLink(string $resourceUrl, string $url, string $filesDirectory, $isCanonicalLink = false): string
    {
        if ($isCanonicalLink) {
            $pageFileName = $this->getPageFileName($url);
            return "{$filesDirectory}/{$pageFileName}";
        }
        $formattedHost = preg_replace('/\W/', '-', parse_url($url, PHP_URL_HOST));
        if (filter_var($resourceUrl, FILTER_VALIDATE_URL)) {
            $formattedPath = str_replace('/', '-', parse_url($resourceUrl, PHP_URL_PATH));
        } else {
            $formattedPath = str_replace('/', '-', $resourceUrl);
        }

        return "{$filesDirectory}/{$formattedHost}{$formattedPath}";
    }

    private function isLocalResource(string $resourceUrl, string $url): bool
    {
        if (filter_var($resourceUrl, FILTER_VALIDATE_URL) === false) {
            return true;
        }
        return parse_url($resourceUrl, PHP_URL_HOST) === parse_url($url, PHP_URL_HOST);
    }
}
