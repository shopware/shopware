<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
interface SnippetValidatorInterface
{
    /**
     * @param array<int, string> $bundles
     *
     * @return array<string, mixed>
     */
    public function validate(array $bundles): array;
}
