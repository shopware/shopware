<?php declare(strict_types=1);

namespace Shopware\Docs\Command\Script;

/**
 * @internal
 */
interface ScriptReferenceGenerator
{
    /**
     * @return array<string, string>
     */
    public function generate(): array;
}
