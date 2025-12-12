<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Definition;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentDefinition;
use Shopware\Core\System\Consent\ConsentScope;

/**
 * @codeCoverageIgnore
 */
#[Package('data-services')]
class Tracking implements ConsentDefinition
{
    public function getName(): string
    {
        return 'tracking';
    }

    public function getScope(): ConsentScope
    {
        return ConsentScope::ADMIN_USER;
    }

    public function getSince(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2025-12-12');
    }
}
