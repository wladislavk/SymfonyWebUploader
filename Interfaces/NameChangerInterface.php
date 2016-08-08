<?php
namespace VKR\SymfonyWebUploader\Interfaces;

interface NameChangerInterface
{
    /**
     * @param string $originalFilename
     * @return string Changed filename
     */
    public function changeName($originalFilename);

    /**
     * @param array $parameters
     */
    public function setParameters(array $parameters);
}
