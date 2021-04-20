<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Context\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class InvalidContextSourceException extends ShopwareHttpException
{
    public function __construct(string $expected, string $actual, ?\Throwable $previous = null)
    {
        parent::__construct(
            'Expected ContextSource of "{{expected}}", but got "{{actual}}".',
            ['expected' => $expected, 'actual' => $actual],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_CONTEXT_SOURCE';
    }
}
