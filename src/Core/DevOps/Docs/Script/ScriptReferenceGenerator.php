<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Docs\Script;

/**
 * @internal
 *
 * @package core
 */
interface ScriptReferenceGenerator
{
    /**
     * @return array<string, string>
     */
    public function generate(): array;
}
