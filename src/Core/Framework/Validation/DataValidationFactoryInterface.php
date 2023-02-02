<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Annotation\Concept\DeprecationPattern\ReplaceDecoratedInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @ReplaceDecoratedInterface(
 *     deprecatedInterface="ValidationServiceInterface",
 *     replacedBy="DataValidationFactoryInterface"
 * )
 */
#[Package('core')]
interface DataValidationFactoryInterface
{
    public function create(SalesChannelContext $context): DataValidationDefinition;

    public function update(SalesChannelContext $context): DataValidationDefinition;
}
