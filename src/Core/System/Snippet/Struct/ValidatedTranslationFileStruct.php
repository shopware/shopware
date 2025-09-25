<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
readonly class ValidatedTranslationFileStruct
{
    private FixableTranslationFileCollection $fixableFiles;

    private TranslationFileCollection $fixingCollection;

    public function __construct(
        private TranslationFileCollection $translationFiles = new TranslationFileCollection([]),
        private TranslationFileCollection $countrySpecificFiles = new TranslationFileCollection([]),
    ) {
        $this->fixableFiles = new FixableTranslationFileCollection([]);
        $this->fixingCollection = new TranslationFileCollection([]);
    }

    public function getCompleteCollection(): TranslationFileCollection
    {
        return $this->translationFiles;
    }

    public function getDomainCollection(string $domain): TranslationFileCollection
    {
        return $this->translationFiles->filter(
            fn (TranslationFile $file) => $this->getCollectionDomainName($file->domain) === self::getCollectionDomainName($domain)
        );
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
        $this->fixableFiles->add($translationFile);
    }

    /**
     * @description List of all {@see TranslationFile}s, grouped by their missing agnostic counterpart
     */
    public function getFixableFiles(): FixableTranslationFileCollection
    {
        return $this->fixableFiles;
    }

    public function getFixableFileCount(): int
    {
        return \array_reduce(
            $this->getFixableFiles()->getElements(),
            static fn ($sum, $fixableFile) => $sum + \count($fixableFile),
            0,
        );
    }

    /**
     * @description List of all {@see TranslationFile}s, which are missing an agnostic counterpart
     *
     * @return list<TranslationFile>
     */
    public function getIssues(): array
    {
        return \array_reduce(
            $this->getFixableFiles()->getElements(),
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
    private function getCollectionDomainName(string $domain): string
    {
        return \in_array($domain, ['administration', 'messages'], true) ? $domain : 'storefront';
    }
}
