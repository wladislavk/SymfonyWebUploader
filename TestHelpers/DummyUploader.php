<?php
namespace VKR\SymfonyWebUploader\TestHelpers;

use VKR\SymfonyWebUploader\Services\AbstractUploader;

class DummyUploader extends AbstractUploader
{
    public function upload()
    {
        $newUrl = $this->uploadURL . '/' . $this->filename;
        copy($this->file->getPathname(), $newUrl);
        return $this;
    }
}
