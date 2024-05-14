<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting;

use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductConfiguratorSettingExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(\Exception $e): ?\Exception
    {
        if (preg_match('/SQLSTATE\[23000]:.*1062 Duplicate.*product_configurator_setting\.uniq\.product_configurator_setting\.prod_id\.vers_id\.prop_group_id\'/', $e->getMessage())) {
            return ProductException::configurationOptionAlreadyExists();
        }

        return null;
    }
}
