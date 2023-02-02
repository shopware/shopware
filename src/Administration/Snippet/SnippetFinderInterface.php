<?php declare(strict_types=1);

namespace Shopware\Administration\Snippet;

interface SnippetFinderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function findSnippets(string $locale): array;
}
