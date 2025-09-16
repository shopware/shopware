<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('discovery')]
class ValidatedTranslationFileStruct extends Struct
{
    /**
     * @var array<string, array<string, TranslationFile>> List of files that need to be fixed (key: agnostic identifier, value: list of locales)
     */
    private array $fixableFilesMapping = [];

    private readonly TranslationFileCollection $fixingCollection;

    public function __construct(
        private readonly TranslationFileCollection $translationFiles = new TranslationFileCollection([]),
        private readonly TranslationFileCollection $countrySpecificFiles = new TranslationFileCollection([]),
    ) {
        $this->fixingCollection = new TranslationFileCollection([]);
    }

    public function getCompleteCollection(): TranslationFileCollection
    {
        return $this->translationFiles;
    }

    public function getDomainCollection(string $domain): TranslationFileCollection
    {
        return $this->translationFiles->filter(static fn (TranslationFile $file) => self::getCollectionDomainName($file->getDomain()) === self::getCollectionDomainName($domain));
    }

    public function getSpecificCollection(): TranslationFileCollection
    {
        return $this->countrySpecificFiles;
    }

    public function getDomainCount(string $domain): int
    {
        return $this->getDomainCollection($domain)->count();
    }

    public function addFixableFile(TranslationFile $translationFile): void
    {
        $this->fixableFilesMapping[$translationFile->getAgnosticPath()][$translationFile->getLocale()] = $translationFile;
    }

    /**
     * @return array<string, array<string, TranslationFile>>
     */
    public function getFixableFiles(): array
    {
        return $this->fixableFilesMapping;
    }

    public function getFixableFileCount(): int
    {
        return \array_reduce(
            $this->getFixableFiles(),
            static fn ($sum, $fixableFile) => $sum + \count($fixableFile),
            0,
        );
    }

    /**
     * @return list<TranslationFile>
     */
    public function getIssues(): array
    {
        return \array_reduce(
            $this->getFixableFiles(),
            static function ($accumulator, $fixableFileOptions) {
                foreach ($fixableFileOptions as $fixableFile) {
                    $accumulator[] = $fixableFile;
                }

                return $accumulator;
            },
            []
        );
    }

    public function addToFixingCollection(TranslationFile $translationFile): void
    {
        $this->fixingCollection->add($translationFile);
    }

    public function getFixingCollection(): TranslationFileCollection
    {
        return $this->fixingCollection;
    }

    /**
     * @description Returns correct collection domain name. All files with a custom domain (e.g. 'swag-cms-extensions' instead of 'messages' or 'storefront')
     *   are no base files and therefore considered storefront files
     */
    public static function getCollectionDomainName(string $domain): string
    {
        return \in_array($domain, ['administration', 'messages'], true) ? $domain : 'storefront';
    }
}
