<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<TranslationFile>
 */
#[Package('discovery')]
class TranslationFileCollection extends Collection
{
    public function add($element): void
    {
        $this->validateType($element);

        $this->set($element->getFullPath(), $element);
    }

    protected function getExpectedClass(): string
    {
        return TranslationFile::class;
    }
}
