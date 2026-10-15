<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ImportExport;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\ImportExportProfileDefinition;
use Shopware\Core\Content\ImportExport\ImportExportProfileTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\Language\LanguageDefinition;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ImportExportProfileTranslationRemovalTest extends TestCase
{
    use KernelTestBehaviour;

    public function testDefinitionAndAssociationsFollowRemovalFlag(): void
    {
        $removed = Feature::isActive('v6.8.0.0');
        $registry = self::getContainer()->get(DefinitionInstanceRegistry::class);

        static::assertSame(!$removed, $registry->has('import_export_profile_translation'));
        static::assertSame(!$removed, self::getContainer()->has(ImportExportProfileTranslationDefinition::class));
        static::assertSame(!$removed, self::getContainer()->get(ImportExportProfileDefinition::class)->getFields()->has('translations'));
        static::assertSame(!$removed, self::getContainer()->get(LanguageDefinition::class)->getFields()->has('importExportProfileTranslations'));

        if (!$removed) {
            static::assertTrue($registry->getByEntityName('import_export_profile_translation')->getFields()->has('label'));
        }
    }
}
