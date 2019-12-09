<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface ValidationServiceInterface
{
    public function buildCreateValidation(SalesChannelContext $context): DataValidationDefinition;

    public function buildUpdateValidation(SalesChannelContext $context): DataValidationDefinition;
}
