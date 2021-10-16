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
     * @var vfsStreamDirectory
     */
    private $root;

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
        $this->root = vfsStream::setup('root');
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
        $url = 'https://ru.hexlet.io/courses';
        $path = 'path/to/file';
        $pathToData = $this->getFixtureFullPath('test.html');
        $data = file_get_contents($pathToData);
        $this->mock->reset();
        $this->mock->append(new Response(200, ['X-Foo' => 'Bar'], $data));
        $fullPathToFile = $this->root->url() . DIRECTORY_SEPARATOR . $path;

        $actualFilePath = $this->pageLoader->downloadPage($url, $fullPathToFile, $this->client);

        //проверить полный путь до файла
        $expectedFileName = 'ru-hexlet-io-courses.html';
        $expextedFilePath = $fullPathToFile . DIRECTORY_SEPARATOR . $expectedFileName;
        $this->assertEquals($expextedFilePath, $actualFilePath);
        //проверить наличие файла в виртуальной ФС
        $this->assertTrue($this->root->hasChild($path . DIRECTORY_SEPARATOR . $expectedFileName));
        //проверить содержимое файла
        $actualDdata = file_get_contents($actualFilePath);
        $this->assertStringEqualsFile($this->getFixtureFullPath('test.html'), $actualDdata);
    }
}
