<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog\Processor;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @internal
 */
#[Package('core')]
class ChangelogValidator extends ChangelogProcessor
{
    /**
     * @return array<string, list<string|\Stringable>>
     */
    public function check(string $path = ''): array
    {
        $errors = [];
        $rootDir = $this->getUnreleasedDir();
        $entries = !empty($path) ? [new SplFileInfo($path, $rootDir, $rootDir)] : $this->getUnreleasedChangelogFiles();
        foreach ($entries as $entry) {
            if (preg_match('/^([-.\w\/]+)$/', $entry->getFilename()) === 0) {
                $errors[$entry->getFileName()][] = 'Changelog has invalid filename, please use only alphanumeric characters, dots, dashes and underscores.';
            }

            $changelog = $this->parser->parse($entry, $rootDir);
            $violations = $this->validator->validate($changelog);

            if (\count($violations)) {
                $errors[$entry->getFileName()] = [];
                foreach ($violations as $violation) {
                    $errors[$entry->getFileName()][] = $violation->getMessage();
                }
            }
        }

        return $errors;
    }

    /**
     * @return list<SplFileInfo>
     */
    private function getUnreleasedChangelogFiles(): array
    {
        $entries = [];
        $finder = new Finder();
        $finder->in($this->getUnreleasedDir())->files()->sortByName()->depth('0')->name('*.md');
        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                $entries[] = $file;
            }
        }

        return $entries;
    }
}
