<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('core')]
class InconsistentCriteriaIdsException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Inconsistent argument for Criteria. Please filter all invalid values first.');
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INCONSISTENT_CRITERIA_IDS';
    }
}
