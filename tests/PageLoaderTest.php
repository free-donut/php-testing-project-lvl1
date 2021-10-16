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

    private $client;

    /**
     * @var string
     */
    private $url;

    /**
     * @var PageLoader
     */
    private $pageLoader;

    public function setUp(): void
    {
        $this->root = vfsStream::setup('root');
        $this->url = 'https://ru.hexlet.io/courses';
        $this->pageLoader = new PageLoader();

        $pathToData = $this->getFixtureFullPath('test.html');
        $data = file_get_contents($pathToData);

        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Bar'], $data),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $this->client = new Client(['handler' => $handlerStack]);
    }

    public function getFixtureFullPath($fixtureName)
    {
        $parts = [__DIR__, 'fixtures', $fixtureName];
        return realpath(implode('/', $parts));
    }

    public function testDownloadPage()
    {
        $path = 'path/to/file';
        $fillPathToFile = $this->root->url() . DIRECTORY_SEPARATOR . $path;
        $expectedFileName = 'ru-hexlet-io-courses.html';
        $expextedFilePath = $fillPathToFile . DIRECTORY_SEPARATOR . $expectedFileName;
        $actualFilePath = $this->pageLoader->downloadPage($this->url, $fillPathToFile, $this->client);

        //проверить полный путь до файла
        $this->assertEquals($expextedFilePath, $actualFilePath);
        //проверить наличие файла в виртуальной ФС
        $this->assertTrue($this->root->hasChild($path . DIRECTORY_SEPARATOR . $expectedFileName));
        //проверить содержимое файла
        $actualDdata = file_get_contents($actualFilePath);
        $this->assertStringEqualsFile($this->getFixtureFullPath('test.html'), $actualDdata);
    }

    public function tearDown(): void
    {
        $this->client = null;
    }
}
