<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Throwable;

class InconsistentCriteriaIdsException extends ShopwareHttpException
{
    public function __construct(int $code = 0, Throwable $previous = null)
    {
        parent::__construct('Inconsistent argument for Criteria. Please filter all invalid values first.', $code, $previous);
    }
}
