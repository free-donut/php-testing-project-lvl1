<?php

namespace Hexlet\Code;

use DiDom\Document;

class PageLoader
{
    public function downloadPage($url, $path, $client): string
    {
        $content = $client->get($url)->getBody()->getContents();
        $document = new Document($content);
        if (count($images = $document->find('img')) > 0) {
            $filesDirectory = $this->getFilesDirectoryName($url);
            $pathToFiles = $path . DIRECTORY_SEPARATOR . $filesDirectory;
            $this->createDir($pathToFiles);
            foreach ($images as $key => $image) {
                $imageUrl = $image->src;
                if ($this->isLocalLink($imageUrl, $url)) {
                    $imageName = $this->getImageName($url, $imageUrl);
                    $newImageLink = $filesDirectory . DIRECTORY_SEPARATOR . $imageName;
                    $document->find('img')[$key]->src = $newImageLink;
                    $imagePath = $path . DIRECTORY_SEPARATOR . $newImageLink;
                    $client->request('GET', $imageUrl, ['sink' => $imagePath]);
                }
            }
            $content = $document->format()->html();
        }
        $this->createDir($path);
        $pageFileName = $this->getPageFileName($url);
        $fullFilePath = $path . DIRECTORY_SEPARATOR . $pageFileName;
        file_put_contents($fullFilePath, $content);
        return $fullFilePath;
    }

    private function getPageFileName($url): string
    {
        $formattedHost = preg_replace('/\W/', '-', parse_url($url, PHP_URL_HOST));
        $formattedPath = preg_replace('/\W/', '-', parse_url($url, PHP_URL_PATH));
        return $formattedHost . $formattedPath . '.html';
    }

    private function createDir($path): self
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        return $this;
    }

    private function getFilesDirectoryName(string $url): string
    {
        $formattedHost = preg_replace('/\W/', '-', parse_url($url, PHP_URL_HOST));
        $formattedPath = preg_replace('/\W/', '-', parse_url($url, PHP_URL_PATH));
        return $formattedHost . $formattedPath . '_files';
    }
    private function getImageName($url, $imageUrl): string
    {
        $formattedHost = preg_replace('/\W/', '-', parse_url($url, PHP_URL_HOST));
        if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $formattedPath = str_replace('/', '-', parse_url($imageUrl, PHP_URL_PATH));
        } else {
            $formattedPath = str_replace('/', '-', $imageUrl);
        }
        return $formattedHost . $formattedPath;
    }

    private function isLocalLink(string $imageUrl, string $url): bool
    {
        if (filter_var($imageUrl, FILTER_VALIDATE_URL) === false) {
            return true;
        }
        return parse_url($imageUrl, PHP_URL_HOST) === parse_url($url, PHP_URL_HOST);
    }
}
