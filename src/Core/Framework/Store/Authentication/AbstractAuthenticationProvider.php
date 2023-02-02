<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Authentication;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Context;

/**
 * @internal
 *
 * @deprecated tag:v6.5.0 - Will be removed use AbstractStoreRequestOptionsProvider instead
 */
abstract class AbstractAuthenticationProvider
{
    abstract public function getUserStoreToken(Context $context): ?string;

    abstract public function getAuthenticationHeader(Context $context): array;

    protected function ensureAdminApiSource(Context $context): AdminApiSource
    {
        $contextSource = $context->getSource();
        if (!($contextSource instanceof AdminApiSource)) {
            throw new InvalidContextSourceException(AdminApiSource::class, \get_class($contextSource));
        }

        return $contextSource;
    }
}
