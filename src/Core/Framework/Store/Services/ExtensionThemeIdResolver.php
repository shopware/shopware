<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
interface ExtensionThemeIdResolver
{
    public function resolveThemeIdByTechnicalName(string $technicalName, Context $context): ?string;
}
