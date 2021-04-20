<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Context\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class InvalidContextSourceUserException extends ShopwareHttpException
{
    public function __construct(string $contextSource, ?\Throwable $previous = null)
    {
        parent::__construct(
            '{{ contextSource }} does not have a valid user ID',
            ['contextSource' => $contextSource],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_CONTEXT_SOURCE_USER';
    }
}
