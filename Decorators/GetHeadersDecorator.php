<?php
namespace VKR\SymfonyWebUploader\Decorators;

class GetHeadersDecorator
{
    /**
     * @param string $url
     * @param int|null $format
     * @return array
     */
    public function getHeaders($url, $format = null)
    {
        return get_headers($url, $format);
    }
}
