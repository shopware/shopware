<?php declare(strict_types=1);

namespace Shopware\Administration\Snippet;

use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
interface SnippetFinderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function findSnippets(string $locale): array;
}
