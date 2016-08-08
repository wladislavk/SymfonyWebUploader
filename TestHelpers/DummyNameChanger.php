<?php
namespace VKR\SymfonyWebUploader\TestHelpers;

use VKR\SymfonyWebUploader\Interfaces\NameChangerInterface;

class DummyNameChanger implements NameChangerInterface
{
    /**
     * @param string $originalFilename
     * @return string Changed filename
     */
    public function changeName($originalFilename)
    {
        return 'changed_' . $originalFilename;
    }

    /**
     * @param array $parameters
     */
    public function setParameters(array $parameters)
    {

    }
}
