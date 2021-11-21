<?php

namespace Hexlet\Code\Tests;

use Hexlet\Code\PageLoader;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Monolog\Logger;

class PageLoaderTest extends TestCase
{
    /**
     * @var string
     */
    private $url;

    /**
     * @var string
     */
    private $expectedFileName;

    /**
     * @var string
     */
    private $path;

    /**
     * @var vfsStreamDirectory
     */
    private $root;

    /**
     * @var string
     */
    private $fullPathToFile;

    /**
     * @var MockHandler
     */
    private $mock;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var PageLoader
     */
    private $pageLoader;

    public function setUp(): void
    {
        $this->url = 'https://ru.hexlet.io/courses';
        $this->expectedFileName = 'ru-hexlet-io-courses.html';
        $this->path = 'path/to/file';
        $this->root = vfsStream::setup('root');
        $this->fullPathToFile = $this->root->url() . DIRECTORY_SEPARATOR . $this->path;
        $stub = $this->createMock(Logger::class);
        $this->pageLoader = new PageLoader($stub);
        $this->mock = new MockHandler([
            new Response(200),
        ]);
        $handlerStack = HandlerStack::create($this->mock);
        $this->client = new Client(['handler' => $handlerStack]);
    }

    public function getFixtureFullPath($fixtureName)
    {
        $parts = [__DIR__, 'fixtures', $fixtureName];
        return realpath(implode('/', $parts));
    }

    public function testDownloadPage()
    {
        $pathToData = $this->getFixtureFullPath('data/data_simple.html');
        $data = file_get_contents($pathToData);
        $this->mock->reset();
        $this->mock->append(new Response(200, ['X-Foo' => 'Bar'], $data));
        $actualFilePath = $this->pageLoader->downloadPage($this->url, $this->fullPathToFile, $this->client);

        //проверить полный путь до файла
        $expextedFilePath = $this->fullPathToFile . DIRECTORY_SEPARATOR . $this->expectedFileName;
        $this->assertEquals($expextedFilePath, $actualFilePath);

        //проверить наличие файла в виртуальной ФС
        $this->assertTrue($this->root->hasChild($this->path . DIRECTORY_SEPARATOR . $this->expectedFileName));

        //проверить отсутствие файла логов в виртуальной ФС
        $this->assertFalse($this->root->hasChild($this->path . DIRECTORY_SEPARATOR . 'info.log"'));

        //проверить содержимое файла
        $actualDdata = file_get_contents($actualFilePath);
        $pathToExpectedData = $this->getFixtureFullPath('expected/expected_simple.html');
        $this->assertStringEqualsFile($pathToExpectedData, $actualDdata);
    }

    public function testDownloadPageWithImages()
    {
        $this->mock->reset();

        $pathToData = $this->getFixtureFullPath('data/data_with_images.html');
        $data = file_get_contents($pathToData);
        $this->mock->append(new Response(200, ['X-Foo' => 'Bar'], $data));

        $pathToImage = $this->getFixtureFullPath('data/resources/42.jpg');
        $imageData = file_get_contents($pathToImage);
        $this->mock->append(new Response(200, [], $imageData));
        $actualFilePath = $this->pageLoader->downloadPage($this->url, $this->fullPathToFile, $this->client);

        //проверить содержимое файла
        $actualDdata = file_get_contents($actualFilePath);
        $pathToExpectedData = $this->getFixtureFullPath('expected/expected_with_images.html');
        $this->assertStringEqualsFile($pathToExpectedData, $actualDdata);

        //проверить наличие изображения в виртуальной ФС
        $imagePath = $this->path . DIRECTORY_SEPARATOR . 'ru-hexlet-io-courses_files/ru-hexlet-io-resources-42.jpg';
        $this->assertTrue($this->root->hasChild($imagePath));

        //сравнить изображения
        $actualImageData = file_get_contents($this->root->url() . DIRECTORY_SEPARATOR . $imagePath);
        $this->assertStringEqualsFile($pathToImage, $actualImageData);
    }

    public function testDownloadPageWithResources()
    {
        $this->mock->reset();

        $pathToData = $this->getFixtureFullPath('data/data_with_resources.html');
        $data = file_get_contents($pathToData);
        $this->mock->append(new Response(200, [], $data));

        $pathToLink = $this->getFixtureFullPath('data/resources/application.css');
        $linkData = file_get_contents($pathToLink);
        $this->mock->append(new Response(200, [], $linkData));

        $pathToLinkCanonical = $this->getFixtureFullPath('data/data_with_resources.html');
        $linkCanonicalData = file_get_contents($pathToLinkCanonical);
        $this->mock->append(new Response(200, [], $linkCanonicalData));

        $pathToScript = $this->getFixtureFullPath('data/resources/js/runtime.js');
        $scriptData = file_get_contents($pathToScript);
        $this->mock->append(new Response(200, [], $scriptData));

        $actualFilePath = $this->pageLoader->downloadPage($this->url, $this->fullPathToFile, $this->client);

        //проверить содержимое файла
        $actualDdata = file_get_contents($actualFilePath);
        $pathToExpectedData = $this->getFixtureFullPath('expected/expected_with_resources.html');
        $this->assertStringEqualsFile($pathToExpectedData, $actualDdata);

        //проверить наличие ресурса в виртуальной ФС
        $linkPath = $this->path . DIRECTORY_SEPARATOR . 'ru-hexlet-io-courses_files/ru-hexlet-io-resources-application.css';
        $this->assertTrue($this->root->hasChild($linkPath));
        $linkCanonicalPath = $this->path . DIRECTORY_SEPARATOR . 'ru-hexlet-io-courses_files/ru-hexlet-io-courses.html';
        $this->assertTrue($this->root->hasChild($linkCanonicalPath));
        $scriptPath = $this->path . DIRECTORY_SEPARATOR . 'ru-hexlet-io-courses_files/ru-hexlet-io-resources-js-runtime.js';
        $this->assertTrue($this->root->hasChild($scriptPath));

        //сравнить данные ресурсов
        $actualLinkData = file_get_contents($this->root->url() . DIRECTORY_SEPARATOR . $linkPath);
        $this->assertStringEqualsFile($pathToLink, $actualLinkData);
        $actualLinkCanonicalData = file_get_contents($this->root->url() . DIRECTORY_SEPARATOR . $linkCanonicalPath);
        $this->assertStringEqualsFile($pathToLinkCanonical, $actualLinkCanonicalData);
        $actualScriptData = file_get_contents($this->root->url() . DIRECTORY_SEPARATOR . $scriptPath);
        $this->assertStringEqualsFile($pathToScript, $actualScriptData);
    }
}
