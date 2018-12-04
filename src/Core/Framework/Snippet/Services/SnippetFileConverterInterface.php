<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Services;

use Shopware\Core\Framework\Snippet\Aggregate\SnippetSet\SnippetSetEntity;

interface SnippetFileConverterInterface
{
    /**
     * Converts A complete SnippetSet
     * and merge all available data from files and database
     *
     * @param SnippetSetEntity $snippetSet
     *
     * @return array
     */
    public function convert(SnippetSetEntity $snippetSet): array;
}
