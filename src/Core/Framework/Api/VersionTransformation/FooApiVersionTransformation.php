<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\VersionTransformation;

use Shopware\Core\Content\Product\ProductActionController;

class FooApiVersionTransformation implements ApiVersionTransformation
{
    public function getVersion(): int
    {
        return 10;
    }

    public function getControllerAction(): string
    {
        return ProductActionController::class . '::fooLatest';
    }
}
