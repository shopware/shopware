<?php declare(strict_types=1);

namespace Shopware\Administration\Snippet;

use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
interface SnippetFilesFinderInterface
{
    /**
     * @return string[]
     */
    public function findSnippetFiles(string $locale): array;
}
