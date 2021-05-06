<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;

class AdrTest extends TestCase
{
    public function testAdrInIndex(): void
    {
        $adrs = [];
        $finder = new Finder();

        try {
            $finder->in(__DIR__ . '/../../../../adr/')->files()->sortByName()->depth('0')->name('*.md')->notName('_template.md')->notName('index.md');
        } catch (DirectoryNotFoundException $e) {
            static::markTestSkipped('Adr Directory does not exist.');
        }

        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                $adrs[] = (string) $file->getFilename();
            }
        }

        $notFound = [];
        $indexContents = file_get_contents(__DIR__ . '/../../../../adr/index.md');
        foreach ($adrs as $adr) {
            if (!str_contains($indexContents, $adr)) {
                $notFound[] = $adr;
            }
        }

        static::assertEmpty($notFound, 'Missing ADRs in adr/index.md.' . \PHP_EOL . 'New ADRs should be added to the index.md.' . \PHP_EOL . print_r($notFound, true));
    }
}
