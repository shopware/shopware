<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\VersionTransformation;

class FooApiVersionTransformation implements ApiVersionTransformation
{
    public function getVersion(): int
    {
        return 10;
    }

    public function getRoute(): string
    {
        return 'api.action.product.foo';
    }
}
