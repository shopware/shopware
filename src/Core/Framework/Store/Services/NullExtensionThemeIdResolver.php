<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
final class NullExtensionThemeIdResolver implements ExtensionThemeIdResolver
{
    public function resolveThemeIdByTechnicalName(string $technicalName, Context $context): ?string
    {
        return null;
    }
}
