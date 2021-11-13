<?php

namespace Hexlet\Code\Tests;

use Hexlet\Code\PageLoader;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

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
        $this->pageLoader = new PageLoader();
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

        $pathToLinkResource = $this->getFixtureFullPath('data/resources/application.css');
        $linkResourceData = file_get_contents($pathToLinkResource);
        $this->mock->append(new Response(200, [], $linkResourceData));

        $pathToScriptResource = $this->getFixtureFullPath('data/resources/js/runtime.js');
        $scriptResourceData = file_get_contents($pathToScriptResource);
        $this->mock->append(new Response(200, [], $scriptResourceData));

        $actualFilePath = $this->pageLoader->downloadPage($this->url, $this->fullPathToFile, $this->client);

        //проверить содержимое файла
        $actualDdata = file_get_contents($actualFilePath);
        $pathToExpectedData = $this->getFixtureFullPath('expected/expected_with_resources.html');
        $this->assertStringEqualsFile($pathToExpectedData, $actualDdata);

        //проверить наличие ресурса в виртуальной ФС
        $linkResourcePath = $this->path . DIRECTORY_SEPARATOR . 'ru-hexlet-io-courses_files/ru-hexlet-io-resources-application.css';
        $this->assertTrue($this->root->hasChild($linkResourcePath));
        $scriptResourcePath = $this->path . DIRECTORY_SEPARATOR . 'ru-hexlet-io-courses_files/ru-hexlet-io-resources-js-runtime.js';
        $this->assertTrue($this->root->hasChild($scriptResourcePath));

        //сравнить данные ресурсов
        $actualLinkResourseData = file_get_contents($this->root->url() . DIRECTORY_SEPARATOR . $linkResourcePath);
        $this->assertStringEqualsFile($pathToLinkResource, $actualLinkResourseData);
        $actualScriptResourseData = file_get_contents($this->root->url() . DIRECTORY_SEPARATOR . $scriptResourcePath);
        $this->assertStringEqualsFile($pathToScriptResource, $actualScriptResourseData);
    }
}
