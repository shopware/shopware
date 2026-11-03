<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Cleanup;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\Cleanup\CleanupUnusedDocumentMediaTaskHandler;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('inventory')]
class CleanupUnusedDocumentMediaTaskHandlerTest extends TestCase
{
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testDeletesOnlyOldUnusedFilesOfTheDocumentsFolder(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();
        $documentsFolder = $this->getFolderId(ProductDocumentDefinition::ENTITY_NAME, $context);
        $productImagesFolder = $this->getFolderId(ProductDefinition::ENTITY_NAME, $context);
        $twoDaysAgo = new \DateTimeImmutable('-2 days');

        $context->scope(Context::SYSTEM_SCOPE, static function (Context $context) use ($ids, $documentsFolder, $productImagesFolder, $twoDaysAgo): void {
            static::getContainer()->get('media.repository')->create([
                self::file($ids->create('unused'), $documentsFolder, $twoDaysAgo),
                self::file($ids->create('manual'), $documentsFolder, $twoDaysAgo),
                self::file($ids->create('handbuch'), $documentsFolder, $twoDaysAgo),
                self::file($ids->create('just-uploaded'), $documentsFolder, new \DateTimeImmutable()),
                self::file($ids->create('product-image'), $productImagesFolder, $twoDaysAgo),
            ], $context);
        });

        $product = (new ProductBuilder($ids, 'drill'))->price(10)->build();
        $product['documents'] = [[
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['title' => 'Manual', 'mediaId' => $ids->get('manual')],
                $this->getDeDeLanguageId() => ['title' => 'Handbuch', 'mediaId' => $ids->get('handbuch')],
            ],
        ]];
        static::getContainer()->get('product.repository')->create([$product], $context);

        static::getContainer()->get(CleanupUnusedDocumentMediaTaskHandler::class)->run();

        $files = $ids->getList(['unused', 'manual', 'handbuch', 'just-uploaded', 'product-image']);
        $remaining = static::getContainer()->get('media.repository')->searchIds(new Criteria(array_values($files)), $context)->getIds();
        static::assertEqualsCanonicalizing(['manual', 'handbuch', 'just-uploaded', 'product-image'], array_keys(array_intersect($files, $remaining)));
    }

    private function getFolderId(string $entity, Context $context): string
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('defaultFolder.entity', $entity));
        $folderId = static::getContainer()->get('media_folder.repository')->searchIds($criteria, $context)->firstId();
        static::assertIsString($folderId);

        return $folderId;
    }

    /**
     * @return array<string, mixed>
     */
    private static function file(string $id, string $folderId, \DateTimeImmutable $uploadedAt): array
    {
        return [
            'id' => $id,
            'fileName' => 'file-' . $id,
            'fileExtension' => 'pdf',
            'mimeType' => 'application/pdf',
            'mediaFolderId' => $folderId,
            'uploadedAt' => $uploadedAt,
        ];
    }
}
