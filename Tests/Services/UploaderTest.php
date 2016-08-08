<?php
namespace VKR\SymfonyWebUploader\Tests\Services;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use VKR\SymfonyWebUploader\Decorators\GetHeadersDecorator;
use VKR\SymfonyWebUploader\TestHelpers\DummyNameChanger;
use VKR\SymfonyWebUploader\TestHelpers\DummyUploader;

class UploaderTest extends \PHPUnit_Framework_TestCase
{
    const SOURCE_DIR = __DIR__ . '/../../TestHelpers/static/source/';
    const SETTINGS_CLASS_NAME = 'VKR\SettingsBundle\Services\SettingsRetriever';

    protected $settings = [
        'destination_dir' => __DIR__ . '/../../TestHelpers/static/destination/',
        'allowed_upload_size' => '1000',
        'allowed_upload_types' => [
            'video/mp4',
            'text/plain',
        ],
    ];

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $settingsRetriever;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $decorator;

    /**
     * @var DummyUploader
     */
    protected $dummyUploader;

    public function setUp()
    {
        $this->mockGetHeadersDecorator();
        $this->dummyUploader = new DummyUploader(null, $this->settings, $this->decorator);
    }

    public function testInitializationWithoutSettings()
    {
        $this->setExpectedException(\RuntimeException::class, 'Either $settingsRetriever or $settings must be defined');
        $dummyUploader = new DummyUploader();
    }

    public function testInitializationWithSettingsObject()
    {
        if (!class_exists(self::SETTINGS_CLASS_NAME)) {
            return;
        }
        $this->mockSettingsRetriever();
        $dummyUploader = new DummyUploader($this->settingsRetriever, [], $this->decorator);
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals('1000', $this->settingsRetriever->get('allowed_upload_size'));
    }

    public function testSetFileWithoutDir()
    {
        $file = new File(self::SOURCE_DIR . 'test.txt');
        $this->setExpectedException(FileException::class, 'Remote upload directory not set. Call setUploadDir() first');
        $this->dummyUploader->setFile($file);
    }

    public function testBadFileType()
    {
        $file = new File(self::SOURCE_DIR . 'my_image.jpg');
        $this->dummyUploader->setUploadDir('destination_dir');
        $this->setExpectedException(FileException::class, 'File type is not allowed');
        $this->dummyUploader->setFile($file);
    }

    public function testBadFileSize()
    {
        $this->settings['allowed_upload_types'][] = 'image/jpeg';
        $dummyUploader = new DummyUploader(null, $this->settings, $this->decorator);
        $file = new File(self::SOURCE_DIR . 'my_image.jpg');
        $dummyUploader->setUploadDir('destination_dir');
        $this->setExpectedException(FileException::class, 'File cannot be bigger than 1000 bytes');
        $dummyUploader->setFile($file);
    }

    public function testMissingSetting()
    {
        unset($this->settings['destination_dir']);
        $dummyUploader = new DummyUploader(null, $this->settings, $this->decorator);
        $this->setExpectedException(\RuntimeException::class, 'Setting destination_dir not found');
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
        $this->mockGetHeadersDecorator(false);
        $dummyUploader = new DummyUploader(null, $this->settings, $this->decorator);
        $file = new File(self::SOURCE_DIR . 'test.txt');
        $dummyUploader->setUploadDir('destination_dir');
        $dummyUploader->setFile($file);
        $newUrl = $dummyUploader->upload();
        $this->setExpectedException(FileException::class, 'File did not upload correctly');
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

    protected function mockGetHeadersDecorator($isSuccessful = true)
    {
        $this->decorator = $this
            ->getMockBuilder(GetHeadersDecorator::class)
            ->disableOriginalConstructor()
            ->getMock();
        if ($isSuccessful) {
            $this->decorator->expects($this->any())
                ->method('getHeaders')
                ->will($this->returnCallback([$this, 'getHeadersCallback']));
            return;
        }
        $this->decorator->expects($this->any())
            ->method('getHeaders')
            ->will($this->returnCallback([$this, 'getHeadersFailureCallback']));
    }

    protected function mockSettingsRetriever()
    {
        $this->settingsRetriever = $this
            ->getMockBuilder(self::SETTINGS_CLASS_NAME)
            ->disableOriginalConstructor()
            ->getMock();
        $this->settingsRetriever->expects($this->any())
            ->method('get')
            ->will($this->returnCallback([$this, 'getMockedSettingValueCallback']));
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
