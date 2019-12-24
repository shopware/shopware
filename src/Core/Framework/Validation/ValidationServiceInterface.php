<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation;

use Shopware\Core\Framework\Context;

/**
 * @deprecated tag:v6.3.0 use the DataValidationFactoryInterface instead
 */
interface ValidationServiceInterface
{
    public function buildCreateValidation(Context $context): DataValidationDefinition;

    public function buildUpdateValidation(Context $context): DataValidationDefinition;
}
