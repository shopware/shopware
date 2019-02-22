<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Filter;

interface SnippetFilterFactoryInterface
{
    public function getFilter(string $name): SnippetFilterInterface;
}
