<?php declare(strict_types=1);

namespace Shopware\Administration\Snippet;

/**
 * @package administration
 */
interface SnippetFinderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function findSnippets(string $locale): array;
}
