<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\MyFakeNamespace;

use Shopware\Core\Framework\Feature;

/**
 * @deprecated tag:v6.8.0 - Will be removed.
 */
class RemovedException extends \RuntimeException
{
    public function __construct()
    {
        Feature::throwIfActive('v6.8.0.0', 'removed');
        parent::__construct('legacy');
    }

    public function getStatusCode(): int
    {
        return 400;
    }

    public function getErrorCode(): string
    {
        Feature::throwIfActive('v6.8.0.0', 'unsafe metadata');

        return 'legacy';
    }

    public static function missingFactoryGuard(): self
    {
        return new self();
    }
}

class ExceptionFactories extends \RuntimeException
{
    /**
     * @deprecated tag:v6.8.0 - Will be removed.
     */
    public static function guardedFactory(): self
    {
        Feature::throwIfActive('v6.8.0.0', 'removed');

        return new self();
    }

    /**
     * @deprecated tag:v6.8.0 - Migrate to replacement.
     */
    public static function ordinaryGuard(): self
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'replacement');

        return new self();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed.
     */
    public static function wrongFactoryFlag(): self
    {
        Feature::throwIfActive('v6.9.0.0', 'removed');

        return new self();
    }
}

/**
 * @deprecated tag:v6.8.0 - Will be removed.
 */
class UnguardedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('unguarded');
    }

    public function getCustomData(): string
    {
        return 'not configured as metadata';
    }
}

class ExistingException extends \RuntimeException
{
    /**
     * @deprecated tag:v6.8.0 - Use the replacement accessor.
     */
    public function deprecatedAccessor(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'replacement');

        return 'legacy';
    }
}
