<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Will be removed. Use `Shopware\Core\System\Snippet\BundleSnippetValidatorInterface` instead
 */
#[Package('discovery')]
interface SnippetValidatorInterface
{
    /**
     * @return array<string, mixed>
     */
    public function validate(): array;
}
