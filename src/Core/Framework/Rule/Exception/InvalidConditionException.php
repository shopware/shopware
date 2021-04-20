<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class InvalidConditionException extends ShopwareHttpException
{
    public function __construct(string $conditionName, ?\Throwable $previous = null)
    {
        parent::__construct('The condition "{{ condition }}" is invalid.', ['condition' => $conditionName], $previous);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_CONDITION_ERROR';
    }
}
