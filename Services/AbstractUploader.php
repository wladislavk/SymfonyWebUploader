<?php
namespace VKR\SymfonyWebUploader\Services;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use VKR\SettingsBundle\Services\SettingsRetriever;
use VKR\SymfonyWebUploader\Decorators\GetHeadersDecorator;
use VKR\SymfonyWebUploader\Interfaces\NameChangerInterface;

/**
 * Abstract class that performs different checks on uploaded file. Actual upload methods are contained in child
 * classes
 */
abstract class AbstractUploader
{
    /**
     * @var null|object
     */
    protected $settingsRetriever;

    /**
     * URL of remote destination folder
     *
     * @var string
     */
    protected $uploadURL;

    /**
     * File handler, same as $_FILES[file]
     *
     * @var File $file
     */
    protected $file;

    /**
     * @var string
     */
    protected $filename;

    /**
     * @var GetHeadersDecorator
     */
    private $decorator;

    /**
     * @var array
     */
    private $settings;

    /**
     * @param object|null $settingsRetriever
     * @param array $settings
     * @param GetHeadersDecorator|null $decorator
     * @throws \RuntimeException
     */
    public function __construct(
        $settingsRetriever = null,
        array $settings = [],
        GetHeadersDecorator $decorator = null
    ) {
        if (is_a($settingsRetriever, SettingsRetriever::class)) {
            $this->settingsRetriever = SettingsRetriever::class;
        }
        if (!$this->settingsRetriever && !sizeof($settings)) {
            throw new \RuntimeException('Either $settingsRetriever or $settings must be defined');
        }
        $this->settings = $settings;
        if ($decorator) {
            $this->decorator = $decorator;
        }
    }

    /**
     * @param string $settingName
     */
    public function setUploadDir($settingName)
    {
        $this->uploadURL = $this->getSetting($settingName);
        $this->uploadURL = rtrim($this->uploadURL, '/');
    }

    /**
     * @return string
     */
    public function getUploadDir()
    {
        return $this->uploadURL;
    }

    /**
     * @return string
     */
    public function getNewFilename()
    {
        return $this->filename;
    }

    /**
     * Checks for file MIME type and duration
     *
     * @param File $file
     * @param NameChangerInterface|null $nameChanger
     * @param bool $shouldCheck
     * @return AbstractUploader
     * @throws FileException
     */
    public function setFile(
        File $file,
        NameChangerInterface $nameChanger = null,
        $shouldCheck = true
    ) {
        if (!$this->uploadURL) {
            throw new FileException('Remote upload directory not set. Call setUploadDir() first');
        }
        $this->file = $file;
        $this->filename = $file->getFilename();
        if ($file instanceof UploadedFile) {
            $this->filename = $file->getClientOriginalName();
        }
        if ($nameChanger) {
            $this->filename = $nameChanger->changeName($this->filename);
        }
        if ($shouldCheck) {
            $this->checkMimeType();
            $this->checkSize();
        }
        return $this;
    }

    /**
     * @return bool
     * @throws FileException
     */
    public function checkIfSuccessful()
    {
        $uploadedFileUrl = $this->uploadURL . '/' . $this->filename;
        $data = $this->getHeaders($uploadedFileUrl, 1);
        $exceptionMessage = "File $uploadedFileUrl did not upload correctly: ";
        if (!isset($data['Content-Length']) || !isset($data['Content-Type'])) {
            throw new FileException($exceptionMessage . 'no file found in destination');
        }
        if (!$data['Content-Length'] || $data['Content-Length'] != $this->file->getSize()) {
            throw new FileException(
                $exceptionMessage . "destination size is {$data['Content-Length']} while original size is {$this->file->getSize()}"
            );
        }
        if (!$data['Content-Type'] || $data['Content-Type'] != $this->file->getMimeType()) {
            throw new FileException(
                $exceptionMessage . "destination type is {$data['Content-Type']} while original type is {$this->file->getMimeType()}"
            );
        }
        return true;
    }

    /**
     * @return AbstractUploader
     */
    abstract public function upload();

    /**
     * @param string $uploadedFileUrl
     * @param int|null $format
     * @return array
     */
    private function getHeaders($uploadedFileUrl, $format)
    {
        if ($this->decorator) {
            return $this->decorator->getHeaders($uploadedFileUrl, $format);
        }
        return get_headers($uploadedFileUrl, $format);
    }

    /**
     * @param string $settingName
     * @param bool $suppressErrors
     * @return bool|string
     * @throws \RuntimeException
     */
    private function getSetting($settingName, $suppressErrors = false)
    {
        if ($this->settingsRetriever) {
            return $this->settingsRetriever->get($settingName, $suppressErrors);
        }
        if ($this->settings) {
            if (isset($this->settings[$settingName])) {
                return $this->settings[$settingName];
            }
        }
        if (!$suppressErrors) {
            throw new \RuntimeException("Setting $settingName not found");
        }
        return false;
    }

    /**
     * Checks if the file MIME type is allowed
     *
     * @throws FileException
     */
    private function checkMimeType()
    {
        $allowedTypes = $this->getSetting('allowed_upload_types', true);
        if (is_string($allowedTypes) && strlen($allowedTypes)) {
            $allowedTypes = str_replace(' ', '', $allowedTypes);
            $allowedTypes = explode(',', $allowedTypes);
        }
        if (is_array($allowedTypes)) {
            if (!in_array($this->file->getMimeType(), $allowedTypes)) {
                throw new FileException('File type is not allowed');
            }
        }
    }

    /**
     * Checks if file is not bigger than certain size
     *
     * @throws FileException
     */
    private function checkSize()
    {
        $allowedSize = intval($this->getSetting('allowed_upload_size', true));
        if ($allowedSize) {
            if ($this->file->getSize() > $allowedSize) {
                throw new FileException("File cannot be bigger than $allowedSize bytes");
            }
        }
    }

}
