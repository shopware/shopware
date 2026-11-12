<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Aggregate\ProductDocument;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentValidator;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('inventory')]
class ProductDocumentValidatorTest extends TestCase
{
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private IdsCollection $ids;

    private Context $context;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->context = Context::createDefaultContext();
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->mediaRepository = static::getContainer()->get('media.repository');

        $this->productRepository->create([(new ProductBuilder($this->ids, 'drill'))->price(10)->build()], $this->context);
    }

    public function testTheFilesOfAllLanguagesAreChecked(): void
    {
        $germanId = $this->getDeDeLanguageId();

        // an Admin API request runs in the user scope, where the media repository hides private files
        $adminApiSource = new AdminApiSource(null);
        $adminApiSource->setIsAdmin(true);

        $errors = $this->errorsOf(fn () => $this->productRepository->update([[
            'id' => $this->ids->get('drill'),
            'documents' => [[
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['title' => 'Manual', 'mediaId' => $this->createMedia('manual')],
                    $germanId => ['title' => 'Handbuch', 'mediaId' => $this->createMedia('handbuch', private: true)],
                ],
            ]],
        ]], Context::createDefaultContext($adminApiSource)));

        // the Admin API user can't see the private file, so it counts as not found
        static::assertSame([
            ['code' => ProductDocumentValidator::VIOLATION_FILE_NOT_FOUND, 'pointer' => '/0/documents/0/translations/' . $germanId . '/mediaId'],
        ], $errors);
    }

    public function testCopiesOfDocumentsWhoseFilesNoLongerFitTheLimitsCanBeMade(): void
    {
        $this->productRepository->update([[
            'id' => $this->ids->get('drill'),
            'documents' => [['mediaId' => $this->createMedia('manual')]],
        ]], $this->context);
        $versionId = $this->productRepository->createVersion($this->ids->get('drill'), $this->context);
        $this->productRepository->update([[
            'id' => $this->ids->get('drill'),
            'documents' => [['id' => $this->ids->create('datasheet'), 'mediaId' => $this->createMedia('datasheet')]],
        ]], $this->context->createWithVersionId($versionId));

        static::getContainer()->get(Connection::class)->executeStatement(
            'UPDATE `media` SET `file_extension` = \'zip\' WHERE `id` IN (:ids)',
            ['ids' => [$this->ids->getBytes('manual'), $this->ids->getBytes('datasheet')]],
            ['ids' => ArrayParameterType::BINARY],
        );

        $this->productRepository->clone($this->ids->get('drill'), $this->context, $this->ids->create('drill.copy'), new CloneBehavior(['productNumber' => 'drill.copy']));
        $this->productRepository->merge($versionId, $this->context);

        $criteria = new Criteria([$this->ids->get('drill'), $this->ids->get('drill.copy')]);
        $criteria->addAssociation('documents');
        $products = $this->productRepository->search($criteria, $this->context)->getEntities();
        static::assertSame([$this->ids->get('manual')], array_values($products->get($this->ids->get('drill.copy'))?->getDocuments()?->map(static fn (ProductDocumentEntity $document) => $document->getMediaId()) ?? []));
        static::assertSame($this->ids->get('datasheet'), $products->get($this->ids->get('drill'))?->getDocuments()?->get($this->ids->get('datasheet'))?->getMediaId());
    }

    public function testADocumentWithoutAFileDoesNotGetTheFileOfAnotherRecordOfTheWrite(): void
    {
        $errors = $this->errorsOf(fn () => $this->productRepository->update([[
            'id' => $this->ids->get('drill'),
            'cover' => ['media' => ['id' => $this->createMedia('cover')]],
            'documents' => [['translations' => [Defaults::LANGUAGE_SYSTEM => ['title' => 'Manual']]]],
        ]], $this->context));

        static::assertSame([
            ['code' => NotBlank::IS_BLANK_ERROR, 'pointer' => '/0/documents/0/translations/' . Defaults::LANGUAGE_SYSTEM . '/mediaId'],
        ], $errors);
    }

    private function createMedia(string $fileName, bool $private = false): string
    {
        $this->mediaRepository->create([[
            'id' => $this->ids->create($fileName),
            'fileName' => $fileName,
            'fileExtension' => 'pdf',
            'fileSize' => 1024,
            'private' => $private,
        ]], $this->context);

        return $this->ids->get($fileName);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function errorsOf(\Closure $write): array
    {
        try {
            $write();
        } catch (WriteException $exception) {
            $errors = [];
            foreach ($exception->getErrors() as $error) {
                $errors[] = ['code' => $error['code'], 'pointer' => $error['source']['pointer'] ?? null];
            }

            return $errors;
        }

        static::fail('The write did not fail.');
    }
}
