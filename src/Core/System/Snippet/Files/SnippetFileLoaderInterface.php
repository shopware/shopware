<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Files;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;

/**
 * @Decoratable
 */
#[Package('system-settings')]
interface SnippetFileLoaderInterface
{
    public function loadSnippetFilesIntoCollection(SnippetFileCollection $snippetFileCollection): void;
}
