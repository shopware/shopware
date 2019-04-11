<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\VersionTransformation;

use Shopware\Core\Content\Product\ProductActionController;

class FooApiVersionTransformation implements ApiVersionTransformation
{
    public static function getVersion(): int
    {
        return 10;
    }

    public static function getControllerAction(): string
    {
        return ProductActionController::class . '::fooLatest';
    }
}
