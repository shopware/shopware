<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Filter;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\SnippetService;

/**
 * @phpstan-import-type SnippetArray from SnippetService
 */
#[Package('system-settings')]
interface SnippetFilterInterface
{
    public function getName(): string;

    public function supports(string $name): bool;

    /**
     * @param SnippetArray             $snippets
     * @param true|string|list<string> $requestFilterValue
     *
     * @return SnippetArray
     */
    public function filter(array $snippets, $requestFilterValue): array;
}
