<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet;

interface SnippetFlattenerInterface
{
    /**
     * Flattens a array with keys
     *
     * Example:
     * from:    [a => [b => [c => 1]]]
     * to:      [a.b.c => 1]
     */
    public function flatten(array $snippets, string $prefix = '', ?array $additionalParameters = null): array;

    /**
     * Unflattens a flatten array (explode by ".")
     *
     * Example:
     * from:    [a.b.c => 1]
     * to:      [a => [b => [c => 1]]]
     *
     * @param SnippetEntity[] $snippets
     */
    public function unflatten(array $snippets): array;
}
