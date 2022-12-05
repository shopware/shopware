<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

/**
 * @package system-settings
 */
interface SnippetValidatorInterface
{
    public function validate(): array;
}
