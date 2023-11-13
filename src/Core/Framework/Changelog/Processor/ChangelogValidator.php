<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog\Processor;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Finder\Finder;

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
        $entries = !empty($path) ? [$path] : $this->getUnreleasedChangelogFiles();
        foreach ($entries as $entry) {
            if (preg_match('/^([-\.\w\/]+)$/', $entry) === 0) {
                $errors[$entry][] = 'Changelog has invalid filename, please use only alphanumeric characters, dots, dashes and underscores.';
            }

            $changelog = $this->parser->parse((string) file_get_contents($entry));
            $violations = $this->validator->validate($changelog);
            if (\count($violations)) {
                $errors[$entry] = [];
                foreach ($violations as $violation) {
                    $errors[$entry][] = $violation->getMessage();
                }
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function getUnreleasedChangelogFiles(): array
    {
        $entries = [];
        $finder = new Finder();
        $finder->in($this->getUnreleasedDir())->files()->sortByName()->depth('0')->name('*.md');
        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                $entries[] = (string) $file->getRealPath();
            }
        }

        return $entries;
    }
}
