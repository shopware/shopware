<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing\Annotation;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @Annotation
 *
 * @deprecated tag:v6.6.0 - Will be removed use `defaults: {"_noStore"=true}` instead
 */
#[Package('storefront')]
class NoStore
{
    final public const ALIAS = 'noStore';

    public function getAliasName(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0')
        );

        return self::ALIAS;
    }

    public function allowArray(): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0')
        );

        return false;
    }
}
