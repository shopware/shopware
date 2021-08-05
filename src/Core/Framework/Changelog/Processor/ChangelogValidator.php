<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog\Processor;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Validator\ConstraintViolationInterface;

class ChangelogValidator extends ChangelogProcessor
{
    public function check(string $path = ''): array
    {
        $errors = [];
        $entries = !empty($path) ? [$path] : $this->getUnreleasedChangelogFiles();
        foreach ($entries as $entry) {
            $changelog = $this->parser->parse((string) file_get_contents($entry));
            $violations = $this->validator->validate($changelog);
            if (\count($violations)) {
                $errors[$entry] = [];
                /** @var ConstraintViolationInterface $violation */
                foreach ($violations as $violation) {
                    $errors[$entry][] = $violation->getMessage();
                }
            }
        }

        return $errors;
    }

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
