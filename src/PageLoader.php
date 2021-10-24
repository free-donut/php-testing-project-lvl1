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
            foreach ($images as $image) {
                $imageName = $this->getImageName($url, $image->src);
                $newImageLink = $filesDirectory . DIRECTORY_SEPARATOR . $imageName;
                $content = str_replace($image->src, $newImageLink, $content);
                $imagePath = $path . DIRECTORY_SEPARATOR . $newImageLink;
                $client->request('GET', $image->src, ['sink' => $imagePath]);
            }
        }
        $this->createDir($path);
        $pageFileName = $this->getPageFileName($url);
        $fullFilePath = $path . DIRECTORY_SEPARATOR . $pageFileName;
        file_put_contents($fullFilePath, $content);
        return $fullFilePath;
    }

    private function getPageFileName($url): string
    {
        $formattedUrl = preg_replace('/\W/', '-', parse_url($url, PHP_URL_HOST));
        $formattedPath = preg_replace('/\W/', '-', parse_url($url, PHP_URL_PATH));
        return $formattedUrl . $formattedPath . '.html';
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
        $formattedUrl = preg_replace('/\W/', '-', parse_url($url, PHP_URL_HOST));
        $formattedPath = preg_replace('/\W/', '-', parse_url($url, PHP_URL_PATH));
        return $formattedUrl . $formattedPath . '_files';
    }
    private function getImageName($url, $path): string
    {
        $formattedUrl = preg_replace('/\W/', '-', parse_url($url, PHP_URL_HOST));
        $formattedPath = str_replace('/', '-', $path);
        return $formattedUrl . $formattedPath;
    }
}
