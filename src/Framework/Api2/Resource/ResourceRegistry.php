<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\Resource;

class ResourceRegistry
{
    /**
     * @var ApiResource[]
     */
    private $resources = [];

    /**
     * @param ApiResource[] ...$resources
     */
    public function __construct(ApiResource ...$resources)
    {
        $this->resources = $resources;
    }

    /**
     * @param string $className
     * @return ApiResource
     */
    public function get(string $className): ApiResource
    {
        foreach ($this->resources as $resource) {
            if ($resource instanceof $className) {
                return $resource;
            }
        }
    }
}