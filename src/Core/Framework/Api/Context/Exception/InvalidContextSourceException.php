<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Context\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('core')]
class InvalidContextSourceException extends ShopwareHttpException
{
    public function __construct(
        string $expected,
        string $actual
    ) {
        parent::__construct(
            'Expected ContextSource of "{{expected}}", but got "{{actual}}".',
            ['expected' => $expected, 'actual' => $actual]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_CONTEXT_SOURCE';
    }
}
