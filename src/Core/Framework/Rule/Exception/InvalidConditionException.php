<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('business-ops')]
class InvalidConditionException extends ShopwareHttpException
{
    public function __construct(string $conditionName)
    {
        parent::__construct('The condition "{{ condition }}" is invalid.', ['condition' => $conditionName]);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_CONDITION_ERROR';
    }
}
