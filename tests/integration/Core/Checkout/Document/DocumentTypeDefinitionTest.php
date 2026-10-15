<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentTypeTranslation\DocumentTypeTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\Language\LanguageDefinition;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentTypeDefinitionTest extends TestCase
{
    use KernelTestBehaviour;

    public function testFrameworkMetadataRemainsAvailableWhileRemovalIsPending(): void
    {
        $registry = self::getContainer()->get(DefinitionInstanceRegistry::class);
        $definition = $registry->getByEntityName('document_type');
        $translation = $registry->getByEntityName('document_type_translation');

        static::assertSame(self::getContainer()->get(DocumentTypeDefinition::class), $definition);
        static::assertSame(self::getContainer()->get(DocumentTypeTranslationDefinition::class), $translation);
        static::assertTrue($definition->getFields()->has('technicalName'));
        static::assertTrue($definition->getFields()->has('translations'));
        static::assertTrue($translation->getFields()->has('name'));
        static::assertTrue($translation->getFields()->has('documentType'));
        static::assertTrue(self::getContainer()->get(LanguageDefinition::class)->getFields()->has('documentTypeTranslations'));
        static::assertSame(DocumentTypeCollection::class, $translation->getParentDefinition()->getCollectionClass());
    }
}
