<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[CoversNothing]
class ADRValidationTest extends TestCase
{
    public function testADRValidation(): void
    {
        $files = Finder::create()
            ->files()
            ->in(__DIR__ . '/../../../../adr')
            ->exclude(['assets'])
            ->name('*.md')
            ->getIterator();

        $all = [];

        foreach ($files as $file) {
            $content = $file->getContents();

            $errors = [];
            if (!(\str_ends_with($file->getPath(), '/adr') || \str_contains($file->getPath(), '/adr/_superseded'))) {
                $errors[] = \sprintf('ADR is inside directory %s. ADR must be in adr/ directory', \substr($file->getPath(), \strlen(__DIR__ . '/../../../../adr')));
            }

            if (!\str_starts_with($content, "---\n")) {
                $errors[] = 'ADR contains no front matter (---) section at the beginning';
                $all[$file->getFilename()] = $errors;

                continue;
            }

            if (\strpos($content, '---', 4) === false) {
                $errors[] = 'ADR contains no front matter (---) section at the end';
                $all[$file->getFilename()] = $errors;

                continue;
            }

            $parsed = \substr($content, 4, \strpos($content, '---', 4) - 5);

            $lines = \explode("\n", $parsed);

            $properties = [];
            foreach ($lines as $line) {
                $parts = \explode(':', $line, 2);
                $properties[trim($parts[0])] = \trim($parts[1]);
            }

            if (!isset($properties['title'])) {
                $errors[] = 'ADR contains no title';
            }

            if (!isset($properties['area'])) {
                $errors[] = 'ADR contains no area';
            }

            if (!isset($properties['date'])) {
                $errors[] = 'ADR contains no date';
            }

            if (!isset($properties['tags'])) {
                $errors[] = 'ADR contains no keywords';
            }

            if (empty($errors)) {
                continue;
            }

            $all[$file->getFilename()] = $errors;
        }

        static::assertEmpty($all, \print_r($all, true));
    }
}
