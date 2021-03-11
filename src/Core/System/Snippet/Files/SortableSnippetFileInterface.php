<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Files;

interface SortableSnippetFileInterface extends SnippetFileInterface
{
    /**
     * Priority to sort translations and overwrite snippets with higher priorities
     */
    public function getPriority(): int;
}
