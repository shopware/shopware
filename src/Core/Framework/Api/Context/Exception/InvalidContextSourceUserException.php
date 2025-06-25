<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Context\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @deprecated tag:v6.8.0 - reason:remove-exception - Will be removed in v6.8.0.0. Use `\Shopware\Core\Framework\Store\StoreException::invalidContextSourceUser` instead.
 */
#[Package('framework')]
class InvalidContextSourceUserException extends ShopwareHttpException
{
    public function __construct(string $contextSource)
    {
        parent::__construct(
            '{{ contextSource }} does not have a valid user ID',
            ['contextSource' => $contextSource]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_CONTEXT_SOURCE_USER';
    }
}
