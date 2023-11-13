<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Docs\Script;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
interface ScriptReferenceGenerator
{
    /**
     * @return array<string, string>
     */
    public function generate(): array;
}
