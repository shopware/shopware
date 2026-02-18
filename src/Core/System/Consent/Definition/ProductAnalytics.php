<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Definition;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentDefinition;
use Shopware\Core\System\Consent\ConsentScope;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('data-services')]
class ProductAnalytics implements ConsentDefinition
{
    public function getName(): string
    {
        return 'product_analytics';
    }

    public function getScopeName(): string
    {
        return ConsentScope\AdminUser::NAME;
    }

    public function getSince(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2025-12-12');
    }

    public function getRequiredPermissions(): array
    {
        return ['user.update_profile'];
    }
}
