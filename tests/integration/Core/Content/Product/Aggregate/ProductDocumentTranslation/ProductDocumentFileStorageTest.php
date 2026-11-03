<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Aggregate\ProductDocumentTranslation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentFileStorage;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('inventory')]
class ProductDocumentFileStorageTest extends TestCase
{
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private IdsCollection $ids;

    private ProductDocumentFileStorage $storage;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->storage = static::getContainer()->get(ProductDocumentFileStorage::class);

        static::getContainer()->get('media.repository')->create(
            array_map(fn (string $name): array => ['id' => $this->ids->create($name), 'fileName' => $name, 'fileExtension' => 'pdf', 'mimeType' => 'application/pdf'], ['manual', 'handbuch', 'datasheet']),
            Context::createDefaultContext(),
        );
        $product = (new ProductBuilder($this->ids, 'drill'))->price(10)->build();
        $product['documents'] = [[
            'id' => $this->ids->create('document'),
            'title' => 'Manual',
            'mediaId' => $this->ids->get('manual'),
            'translations' => [$this->getDeDeLanguageId() => ['mediaId' => $this->ids->get('handbuch')]],
        ]];
        static::getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());
    }

    public function testReadsTheFileOfEachLanguageVersion(): void
    {
        static::assertEquals(
            [$this->ids->get('document') => [Defaults::LIVE_VERSION => [Defaults::LANGUAGE_SYSTEM => $this->ids->get('manual'), $this->getDeDeLanguageId() => $this->ids->get('handbuch')]]],
            $this->storage->getFiles([$this->ids->get('document')], [Defaults::LIVE_VERSION]),
        );
    }

    public function testReadsTheParentOfEachLanguageThatHasOne(): void
    {
        $localeId = Uuid::fromBytesToHex((string) static::getContainer()->get(Connection::class)->fetchOne('SELECT `id` FROM `locale` WHERE `code` = \'de-CH\''));
        static::getContainer()->get('language.repository')->create([[
            'id' => $this->ids->create('swiss-german'),
            'name' => 'Deutsch (Schweiz)',
            'parentId' => $this->getDeDeLanguageId(),
            'localeId' => $localeId,
            'translationCodeId' => $localeId,
        ]], Context::createDefaultContext());

        $parentLanguages = $this->storage->getParentLanguages();

        static::assertSame($this->getDeDeLanguageId(), $parentLanguages[$this->ids->get('swiss-german')] ?? null);
        static::assertArrayNotHasKey($this->getDeDeLanguageId(), $parentLanguages);
    }

    public function testMovesOnlyTheVersionsThatStillHaveTheOldFile(): void
    {
        $move = ['documentId' => $this->ids->get('document'), 'versionId' => Defaults::LIVE_VERSION, 'oldFileId' => $this->ids->get('manual'), 'newFileId' => $this->ids->get('datasheet')];

        $this->storage->moveFiles([
            ['languageId' => Defaults::LANGUAGE_SYSTEM, ...$move],
            ['languageId' => $this->getDeDeLanguageId(), ...$move],
        ]);

        static::assertEquals(
            [Defaults::LANGUAGE_SYSTEM => $this->ids->get('datasheet'), $this->getDeDeLanguageId() => $this->ids->get('handbuch')],
            $this->storage->getFiles([$this->ids->get('document')], [Defaults::LIVE_VERSION])[$this->ids->get('document')][Defaults::LIVE_VERSION],
        );
    }
}
