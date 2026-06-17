<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\MyFakeNamespace;

use Shopware\Core\Framework\Feature;

/**
 * @deprecated tag:v6.8.0 - Will be removed without replacement
 */
class DeprecatedClass
{
    public function publicMethodWithoutTrigger(): void
    {
    }

    public function publicMethodWithTrigger(): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0')
        );
    }
}
