<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\System\Snippet\Command\Util\CountryAgnosticFileLinter;

/**
 * @description Contains a collection of {@see TranslationFile}s, which are content to be fixed by {@see CountryAgnosticFileLinter::fixFilenames()}
 *  Files in {@see self::getMapping()} are mapped to their agnostic filepath, which is missing.
 *
 * @example "path/to/file/de.json" maps to the TranslationFiles of "de-DE.json" and "de-AT.json" in the same directory, if "de.json" is missing.
 *
 * @extends Collection<TranslationFile>
 */
#[Package('discovery')]
class FixableTranslationFileCollection extends Collection
{
    /**
     * @var array<string, array<string, TranslationFile>> List of all {@see TranslationFile}s, grouped by their missing agnostic counterpart
     */
    private array $mapping = [];

    public function add($element): void
    {
        parent::add($element);

        $this->mapping[$element->getAgnosticPath()][$element->locale] = $element;
    }

    public function set($key, $element): void
    {
        parent::set($key, $element);

        $this->mapping[$element->getAgnosticPath()][$element->locale] = $element;
    }

    /**
     * @description List of all {@see TranslationFile}s, grouped by their missing agnostic counterpart
     *
     * @return array<string, array<string, TranslationFile>>
     */
    public function getMapping(): array
    {
        return $this->mapping;
    }

    protected function getExpectedClass(): string
    {
        return TranslationFile::class;
    }
}
