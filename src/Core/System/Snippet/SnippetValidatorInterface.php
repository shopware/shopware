<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

interface SnippetValidatorInterface
{
    public function validate(): array;
}
