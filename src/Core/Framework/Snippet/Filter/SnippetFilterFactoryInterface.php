<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Filter;

interface SnippetFilterFactoryInterface
{
    /**
     * @param string $name
     *
     * @return SnippetFilterInterface
     */
    public function getFilter(string $name): SnippetFilterInterface;
}
