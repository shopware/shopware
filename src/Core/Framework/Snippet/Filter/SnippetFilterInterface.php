<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Filter;

interface SnippetFilterInterface
{
    public function getName(): string;

    public function supports(string $name): bool;

    public function filter(array $snippets, $requestFilterValue): array;
}
