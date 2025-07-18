<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Struct\SnippetValidationStruct;

#[Package('discovery')]
interface SnippetValidatorInterface
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function validate(): array;

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use validate() instead
     */
    public function getValidation(): SnippetValidationStruct;
}
