<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Authentication;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('merchant-services')]
abstract class AbstractStoreRequestOptionsProvider
{
    /**
     * @return array<string, string>
     */
    abstract public function getAuthenticationHeader(Context $context): array;

    /**
     * @return array<string, string>
     */
    abstract public function getDefaultQueryParameters(Context $context): array;

    protected function ensureAdminApiSource(Context $context): AdminApiSource
    {
        $contextSource = $context->getSource();
        if (!($contextSource instanceof AdminApiSource)) {
            throw new InvalidContextSourceException(AdminApiSource::class, $contextSource::class);
        }

        return $contextSource;
    }
}
