<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation;

use Shopware\Core\Framework\Context;

interface ValidationServiceInterface
{
    public function buildCreateValidation(Context $context): DataValidationDefinition;

    public function buildUpdateValidation(Context $context): DataValidationDefinition;
}
