<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Validation;

use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataValidationDefinition;

#[Package('sales-channel')]
interface SeoUrlDataValidationFactoryInterface
{
    public function buildValidation(Context $context, SeoUrlRouteConfig $config): DataValidationDefinition;
}
