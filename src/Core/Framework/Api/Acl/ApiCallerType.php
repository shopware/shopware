<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
enum ApiCallerType
{
    case User;
    case Integration;

    /**
     * An integration always acts as itself, never as the user it names in the `sw-app-user-id` header.
     * Returns null when the source is not an Admin API caller.
     *
     * @return ($source is AdminApiSource ? self : null)
     */
    public static function of(ContextSource $source): ?self
    {
        if (!$source instanceof AdminApiSource) {
            return null;
        }

        return $source->getIntegrationId() !== null ? self::Integration : self::User;
    }

    public function idOf(ContextSource $source): ?string
    {
        if (!$source instanceof AdminApiSource) {
            return null;
        }

        return match ($this) {
            self::Integration => $source->getIntegrationId(),
            self::User => $source->getUserId(),
        };
    }
}
