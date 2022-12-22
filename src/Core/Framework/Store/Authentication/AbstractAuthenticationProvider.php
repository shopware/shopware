<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Authentication;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;

/**
 * @package merchant-services
 *
 * @internal
 *
 * @deprecated tag:v6.5.0 - Will be removed use Shopware\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider instead
 */
abstract class AbstractAuthenticationProvider
{
    abstract public function getUserStoreToken(Context $context): ?string;

    /**
     * @return array<string, string>
     */
    abstract public function getAuthenticationHeader(Context $context): array;

    protected function ensureAdminApiSource(Context $context): AdminApiSource
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', AbstractStoreRequestOptionsProvider::class)
        );

        $contextSource = $context->getSource();
        if (!($contextSource instanceof AdminApiSource)) {
            throw new InvalidContextSourceException(AdminApiSource::class, \get_class($contextSource));
        }

        return $contextSource;
    }
}
