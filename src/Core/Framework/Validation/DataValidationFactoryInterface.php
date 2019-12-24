<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface DataValidationFactoryInterface
{
    public function create(SalesChannelContext $context): DataValidationDefinition;

    public function update(SalesChannelContext $context): DataValidationDefinition;
}
