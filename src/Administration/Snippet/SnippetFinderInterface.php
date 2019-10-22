<?php declare(strict_types=1);

namespace Shopware\Administration\Snippet;

interface SnippetFinderInterface
{
    public function findSnippets(string $locale): array;
}
