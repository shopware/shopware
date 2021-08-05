<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Migration\V6_4\Migration1624967118updateMailTemplatesWithOptionalSalutation as MigrationTested;
use Symfony\Component\Finder\Finder;

class Migration1624967118updateMailTemplatesWithOptionalSalutationTest extends TestCase
{
    private const TEMPLATE_DIR = __DIR__ . '/../Fixtures/mails/';
    private const SALUTATION_ACCESS = '.salutation.';

    public function testMigrationConsidersAllTemplatesAccessingSalutation(): void
    {
        $filesToConsider = (new Finder())->in(self::TEMPLATE_DIR)
            ->contains(self::SALUTATION_ACCESS)
            ->files()
            ->getIterator();

        $templatesToConsider = [];

        foreach ($filesToConsider as $file) {
            $templatesToConsider[] = \str_replace(self::TEMPLATE_DIR, '', $file->getPath());
        }

        static::assertEqualsCanonicalizing(MigrationTested::MAIL_TYPE_DIRS, \array_unique($templatesToConsider));
    }
}
