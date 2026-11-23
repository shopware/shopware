<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\MyFakeNamespace;

/**
 * @deprecated tag:v6.8.0 - Will be removed without replacement
 */
class TaggedDeprecatedClass
{
    public function frameworkInvokedMethod(): void
    {
    }

    /**
     * @deprecated tag:v6.8.0 - Use another method instead
     */
    public function explicitlyDeprecatedMethod(): void
    {
    }
}
