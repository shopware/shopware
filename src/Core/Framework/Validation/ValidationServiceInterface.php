<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\Annotation\Concept\DeprecationPattern\ReplaceDecoratedInterface;

/**
 * @deprecated tag:v6.3.0 use the DataValidationFactoryInterface instead
 * @ReplaceDecoratedInterface(
 *     deprecatedInterface="ValidationServiceInterface",
 *     replacedBy="DataValidationFactoryInterface"
 * )
 */
interface ValidationServiceInterface
{
    public function buildCreateValidation(Context $context): DataValidationDefinition;

    public function buildUpdateValidation(Context $context): DataValidationDefinition;
}
