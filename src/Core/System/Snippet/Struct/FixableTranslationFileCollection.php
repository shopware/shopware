<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\System\Snippet\Command\Util\CountryAgnosticFileValidator;

/**
 * @description Contains a collection of {@see TranslationFile}, which are content to be fixed be {@see CountryAgnosticFileValidator}
 *  Those files are mapped to their agnostic filepath, which is missing.
 * @example "path/to/file/de.json" maps to the TranslationFiles of "de-DE.json" and "de-AT.json" in the same directory, if "de.json" is missing.
 *
 * @extends Collection<TranslationFile>
 */
#[Package('discovery')]
class FixableTranslationFileCollection extends Collection
{
    public function add($element): void
    {
        $this->validateType($element);

        $this->elements[$element->getAgnosticPath()][$element->locale] = $element;
    }

    protected function getExpectedClass(): string
    {
        return TranslationFile::class;
    }
}
