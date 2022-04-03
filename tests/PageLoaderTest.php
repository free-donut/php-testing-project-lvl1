<?php

namespace Downloader\Tests;

use Downloader\Downloader;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
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
     * @var Logger
     */
    private $loggerStub;

    public function setUp(): void
    {
        $this->url = 'https://ru.hexlet.io/courses';
        $this->expectedFileName = 'ru-hexlet-io-courses.html';
        $this->path = 'path/to/file';
        $this->root = vfsStream::setup('root');
        $this->fullPathToFile = $this->root->url() . DIRECTORY_SEPARATOR . $this->path;
        $this->loggerStub = $this->createMock(Logger::class);
        $this->mock = new MockHandler([
            new Response(200),
        ]);
        $handlerStack = HandlerStack::create($this->mock);
        $this->client = new Client(['handler' => $handlerStack]);
    }

    public function getFixtureFullPath(string $fixtureName): string
    {
        $parts = [__DIR__, 'fixtures', $fixtureName];

        return ($path = realpath(implode('/', $parts))) ? $path : '';
    }

    public function testDownloadPage(): void
    {
        $this->root->addChild(vfsStream::newDirectory($this->path));
        $this->mock->reset();
        $this->addMockWhithFixtureData('data/data_simple.html');
        $actualFilePath = Downloader::downloadPage($this->url, $this->fullPathToFile, $this->client, $this->loggerStub);

        //проверить полный путь до файла
        $expextedFilePath = $this->fullPathToFile . DIRECTORY_SEPARATOR . $this->expectedFileName;
        $this->assertEquals($expextedFilePath, $actualFilePath);

        //проверить наличие файла в виртуальной ФС
        $this->assertTrue($this->root->hasChild($this->path . DIRECTORY_SEPARATOR . $this->expectedFileName));

        //проверить отсутствие файла логов в виртуальной ФС
        $this->assertFalse($this->root->hasChild($this->path . '/info.log"'));

        //проверить содержимое файла
        $actualData = file_get_contents($actualFilePath);
        $pathToExpectedData = $this->getFixtureFullPath('expected/expected_simple.html');
        $this->assertStringEqualsFile(
            $pathToExpectedData,
            is_string($actualData) ? $actualData : ''
        );
    }

    public function testDownloadPageWithImages(): void
    {
        $this->root->addChild(vfsStream::newDirectory($this->path));
        $this->mock->reset();

        $this->addMockWhithFixtureData('data/data_with_images.html');
        $this->addMockWhithFixtureData('data/resources/42.jpg');

        $actualFilePath = Downloader::downloadPage($this->url, $this->fullPathToFile, $this->client, $this->loggerStub);

        //проверить содержимое файла
        $actualData = file_get_contents($actualFilePath);
        $pathToExpectedData = $this->getFixtureFullPath('expected/expected_with_images.html');
        $this->assertStringEqualsFile(
            $pathToExpectedData,
            is_string($actualData) ? $actualData : ''
        );

        //проверить наличие изображения в виртуальной ФС
        $imagePath = $this->path . '/ru-hexlet-io-courses_files/ru-hexlet-io-resources-42.jpg';
        $this->assertTrue($this->root->hasChild($imagePath));

        //сравнить изображения
        $actualImageData = file_get_contents($this->root->url() . DIRECTORY_SEPARATOR . $imagePath);
        $this->assertStringEqualsFile(
            $this->getFixtureFullPath('data/resources/42.jpg'),
            is_string($actualImageData) ? $actualImageData : ''
        );
    }

    public function testDownloadPageWithResources(): void
    {
        $this->root->addChild(vfsStream::newDirectory($this->path));
        $this->mock->reset();

        $this->addMockWhithFixtureData('data/data_with_resources.html');
        $this->addMockWhithFixtureData('data/resources/application.css');
        $this->addMockWhithFixtureData('data/data_with_resources.html');
        $this->addMockWhithFixtureData('data/resources/js/runtime.js');

        $actualFilePath = Downloader::downloadPage($this->url, $this->fullPathToFile, $this->client, $this->loggerStub);

        //проверить содержимое файла
        $actualData = file_get_contents($actualFilePath);
        $pathToExpectedData = $this->getFixtureFullPath('expected/expected_with_resources.html');
        $this->assertStringEqualsFile($pathToExpectedData, is_string($actualData) ? $actualData : '');

        //проверить наличие ресурса в виртуальной ФС
        $linkPath = $this->path . '/ru-hexlet-io-courses_files/ru-hexlet-io-resources-application.css';
        $this->assertTrue($this->root->hasChild($linkPath));
        $linkCanonicalPath = $this->path . '/ru-hexlet-io-courses_files/ru-hexlet-io-courses.html';
        $this->assertTrue($this->root->hasChild($linkCanonicalPath));
        $scriptPath = $this->path . '/ru-hexlet-io-courses_files/ru-hexlet-io-resources-js-runtime.js';
        $this->assertTrue($this->root->hasChild($scriptPath));

        //сравнить данные ресурсов
        $actualLinkData = file_get_contents($this->root->url() . DIRECTORY_SEPARATOR . $linkPath);
        $this->assertStringEqualsFile(
            $this->getFixtureFullPath('data/resources/application.css'),
            is_string($actualLinkData) ? $actualLinkData : ''
        );
        $actualLinkCanonicalData = file_get_contents($this->root->url() . DIRECTORY_SEPARATOR . $linkCanonicalPath);
        $this->assertStringEqualsFile(
            $this->getFixtureFullPath('data/data_with_resources.html'),
            is_string($actualLinkCanonicalData) ? $actualLinkCanonicalData : ''
        );
        $actualScriptData = file_get_contents($this->root->url() . DIRECTORY_SEPARATOR . $scriptPath);
        $this->assertStringEqualsFile(
            $this->getFixtureFullPath('data/resources/js/runtime.js'),
            is_string($actualScriptData) ? $actualScriptData : ''
        );
    }

    public function testNotFoundDirectory(): void
    {
        $this->expectExceptionMessage(sprintf('Directory "%s" not found', $this->fullPathToFile));
        Downloader::downloadPage($this->url, $this->fullPathToFile, $this->client, $this->loggerStub);
    }

    public function testUncorrectedStatusCode(): void
    {
        $this->root->addChild(vfsStream::newDirectory($this->path));
        $this->mock->reset();
        $this->mock->append(new Response(404));
        $this->expectException(\Exception::class);
        Downloader::downloadPage($this->url, $this->fullPathToFile, $this->client, $this->loggerStub);
    }

    public function testDirectoryNotCreated(): void
    {
        $this->root->addChild(vfsStream::newDirectory($this->path, 0555));
        $this->mock->reset();
        $this->addMockWhithFixtureData('data/data_with_images.html');
        $this->addMockWhithFixtureData('data/resources/42.jpg');

        $directoryPath = $this->fullPathToFile . '/ru-hexlet-io-courses_files';
        $this->expectExceptionMessage(sprintf('Directory was not created, path "%s"', $directoryPath));
        Downloader::downloadPage($this->url, $this->fullPathToFile, $this->client, $this->loggerStub);
    }

    public function testResourceSaving(): void
    {
        $this->root->addChild(vfsStream::newDirectory($this->path));
        $this->mock->reset();
        $this->addMockWhithFixtureData('data/data_with_images.html');
        $this->addMockWhithFixtureData('data/resources/42.jpg', 301);

        $directoryPath = $this->fullPathToFile . '/ru-hexlet-io-courses_files';
        $resourceUrl = '/resources/42.jpg';
        $expectMessage = sprintf('HTTPstatus code for resource "%s" is "%s". 200 expected', $resourceUrl, 301);
        $this->expectExceptionMessage($expectMessage);
        Downloader::downloadPage($this->url, $this->fullPathToFile, $this->client, $this->loggerStub);
    }

    private function addMockWhithFixtureData(string $path, int $code = 200): void
    {
        $pathToData = $this->getFixtureFullPath($path);
        if ($pathToData) {
            $data = file_get_contents($pathToData);
            $this->mock->append(new Response($code, [], is_string($data) ? $data : ''));
        }
    }
}
