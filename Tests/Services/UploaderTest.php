<?php
namespace VKR\SymfonyWebUploader\Tests\Services;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use VKR\SettingsBundle\Services\SettingsRetriever;
use VKR\SymfonyWebUploader\Decorators\GetHeadersDecorator;
use VKR\SymfonyWebUploader\TestHelpers\DummyNameChanger;
use VKR\SymfonyWebUploader\TestHelpers\DummyUploader;

class UploaderTest extends TestCase
{
    const SOURCE_DIR = __DIR__ . '/../../TestHelpers/static/source/';

    private $settings = [
        'destination_dir' => __DIR__ . '/../../TestHelpers/static/destination/',
        'allowed_upload_size' => '1000',
        'allowed_upload_types' => [
            'video/mp4',
            'text/plain',
        ],
    ];

    /**
     * @var DummyUploader
     */
    private $dummyUploader;

    public function setUp()
    {
        $decorator = $this->mockGetHeadersDecorator();
        $this->dummyUploader = new DummyUploader(null, $this->settings, $decorator);
    }

    public function testInitializationWithoutSettings()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Either $settingsRetriever or $settings must be defined');
        $dummyUploader = new DummyUploader();
    }

    public function testInitializationWithSettingsObject()
    {
        if (!class_exists(SettingsRetriever::class)) {
            return;
        }
        $settingsRetriever = $this->mockSettingsRetriever();
        $decorator = $this->mockGetHeadersDecorator();
        $dummyUploader = new DummyUploader($settingsRetriever, [], $decorator);
        $this->assertTrue(true);
    }

    public function testSetFileWithoutDir()
    {
        $file = new File(self::SOURCE_DIR . 'test.txt');
        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Remote upload directory not set. Call setUploadDir() first');
        $this->dummyUploader->setFile($file);
    }

    public function testBadFileType()
    {
        $file = new File(self::SOURCE_DIR . 'my_image.jpg');
        $this->dummyUploader->setUploadDir('destination_dir');
        $this->expectException(FileException::class);
        $this->expectExceptionMessage('File type is not allowed');
        $this->dummyUploader->setFile($file);
    }

    public function testBadFileSize()
    {
        $this->settings['allowed_upload_types'][] = 'image/jpeg';
        $decorator = $this->mockGetHeadersDecorator();
        $dummyUploader = new DummyUploader(null, $this->settings, $decorator);
        $file = new File(self::SOURCE_DIR . 'my_image.jpg');
        $dummyUploader->setUploadDir('destination_dir');
        $this->expectException(FileException::class);
        $this->expectExceptionMessage('File cannot be bigger than 1000 bytes');
        $dummyUploader->setFile($file);
    }

    public function testMissingSetting()
    {
        unset($this->settings['destination_dir']);
        $decorator = $this->mockGetHeadersDecorator();
        $dummyUploader = new DummyUploader(null, $this->settings, $decorator);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Setting destination_dir not found');
        $dummyUploader->setUploadDir('destination_dir');
    }

    public function testNameChanger()
    {
        $file = new File(self::SOURCE_DIR . 'test.txt');
        $this->dummyUploader->setUploadDir('destination_dir');
        $nameChanger = new DummyNameChanger();
        $this->dummyUploader->setFile($file, $nameChanger);
        $this->dummyUploader->upload();
        $this->assertTrue(is_file($this->settings['destination_dir'] . 'changed_test.txt'));
    }

    public function testUploadedFile()
    {
        $file = new UploadedFile(self::SOURCE_DIR . 'test.txt', 'original_test.txt');
        $this->dummyUploader->setUploadDir('destination_dir');
        $this->dummyUploader->setFile($file);
        $this->dummyUploader->upload();
        $this->assertTrue(is_file($this->settings['destination_dir'] . 'original_test.txt'));
    }

    public function testSuccessfulUpload()
    {
        $file = new File(self::SOURCE_DIR . 'test.txt');
        $this->dummyUploader->setUploadDir('destination_dir');
        $this->dummyUploader->setFile($file);
        $this->assertTrue($this->dummyUploader->upload()->checkIfSuccessful());
    }

    public function testUnsuccessfulUpload()
    {
        $decorator = $this->mockGetHeadersDecorator(false);
        $dummyUploader = new DummyUploader(null, $this->settings, $decorator);
        $filename = self::SOURCE_DIR . 'test.txt';
        $file = new File($filename);
        $dummyUploader->setUploadDir('destination_dir');
        $dummyUploader->setFile($file);
        $newUrl = $dummyUploader->upload();
        $this->expectException(FileException::class);
        $newFilename = str_replace('source', 'destination', $filename);
        $this->expectExceptionMessage("File $newFilename did not upload correctly");
        $dummyUploader->checkIfSuccessful();
    }

    public function tearDown()
    {
        if (!isset($this->settings['destination_dir'])) {
            return;
        }
        $files = glob($this->settings['destination_dir'] . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function mockGetHeadersDecorator($isSuccessful = true)
    {
        $decorator = $this->createMock(GetHeadersDecorator::class);
        if ($isSuccessful) {
            $decorator->method('getHeaders')
                ->willReturnCallback([$this, 'getHeadersCallback']);
            return $decorator;
        }
        $decorator->method('getHeaders')
            ->willReturnCallback([$this, 'getHeadersFailureCallback']);
        return $decorator;
    }

    private function mockSettingsRetriever()
    {
        $settingsRetriever = $this->createMock(SettingsRetriever::class);
        $settingsRetriever->method('get')
            ->will($this->returnCallback([$this, 'getMockedSettingValueCallback']));
        return $settingsRetriever;
    }

    public function getMockedSettingValueCallback($settingName)
    {
        if (isset($this->settings[$settingName])) {
            return $this->settings[$settingName];
        }
        throw new \Exception("Setting not found: $settingName");
    }

    public function getHeadersCallback($url, $format = null)
    {
        $file = new File($url);
        $data = [
            'Content-Length' => $file->getSize(),
            'Content-Type' => $file->getMimeType(),
        ];
        return $data;
    }

    public function getHeadersFailureCallback($url, $format = null)
    {
        $data = [
            'Content-Length' => 0,
            'Content-Type' => 'nonexistent/type',
        ];
        return $data;
    }
}
