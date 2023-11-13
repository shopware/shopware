<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Filter;

use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
interface SnippetFilterInterface
{
    public function getName(): string;

    public function supports(string $name): bool;

    public function filter(array $snippets, $requestFilterValue): array;
}
