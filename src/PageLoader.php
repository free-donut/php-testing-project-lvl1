<?php

namespace Hexlet\Code;

class PageLoader
{
    public function downloadPage($url, $path, $client): string
    {
        $content = $client->get($url)->getBody()->getContents();
        $this->createDir($path);
        $fileName = $this->getFileName($url);
        $fullFilePath = $path . DIRECTORY_SEPARATOR . $this->getFileName($url);
        file_put_contents($fullFilePath, $content);
        return $fullFilePath;
    }

    private function getFileName($url): string
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
}
