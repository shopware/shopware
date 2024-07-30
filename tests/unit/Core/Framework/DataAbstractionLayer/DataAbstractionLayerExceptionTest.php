<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidFilterQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSortQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(DataAbstractionLayerException::class)]
class DataAbstractionLayerExceptionTest extends TestCase
{
    public function testInvalidCronIntervalFormat(): void
    {
        $e = DataAbstractionLayerException::invalidCronIntervalFormat('foo');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(DataAbstractionLayerException::INVALID_CRON_INTERVAL_CODE, $e->getErrorCode());
        static::assertSame('Unknown or bad CronInterval format "foo".', $e->getMessage());
    }

    public function testInvalidDateIntervalFormat(): void
    {
        $e = DataAbstractionLayerException::invalidDateIntervalFormat('foo');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(DataAbstractionLayerException::INVALID_DATE_INTERVAL_CODE, $e->getErrorCode());
        static::assertSame('Unknown or bad DateInterval format "foo".', $e->getMessage());
    }

    public function testInvalidSerializerField(): void
    {
        $e = DataAbstractionLayerException::invalidSerializerField(FkField::class, new IdField('foo', 'foo'));

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(DataAbstractionLayerException::INVALID_FIELD_SERIALIZER_CODE, $e->getErrorCode());
    }

    public function testInvalidCriteriaIds(): void
    {
        $e = DataAbstractionLayerException::invalidCriteriaIds(['foo'], 'bar');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame(DataAbstractionLayerException::INVALID_CRITERIA_IDS, $e->getErrorCode());
    }

    public function testInvalidApiCriteriaIds(): void
    {
        $e = DataAbstractionLayerException::invalidApiCriteriaIds(
            DataAbstractionLayerException::invalidCriteriaIds(['foo'], 'bar')
        );

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(DataAbstractionLayerException::INVALID_API_CRITERIA_IDS, $e->getErrorCode());
    }

    public function testInvalidLanguageId(): void
    {
        $e = DataAbstractionLayerException::invalidLanguageId('foo');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(DataAbstractionLayerException::INVALID_LANGUAGE_ID, $e->getErrorCode());
    }

    public function testInvalidFilterQuery(): void
    {
        $e = DataAbstractionLayerException::invalidFilterQuery('foo', 'baz');

        static::assertInstanceOf(InvalidFilterQueryException::class, $e);
        static::assertEquals('foo', $e->getMessage());
        static::assertEquals('baz', $e->getParameters()['path']);
        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals(DataAbstractionLayerException::INVALID_FILTER_QUERY, $e->getErrorCode());
    }

    public function testInvalidSortQuery(): void
    {
        $e = DataAbstractionLayerException::invalidSortQuery('foo', 'baz');

        static::assertInstanceOf(InvalidSortQueryException::class, $e);
        static::assertEquals('foo', $e->getMessage());
        static::assertEquals('baz', $e->getParameters()['path']);
        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals(DataAbstractionLayerException::INVALID_SORT_QUERY, $e->getErrorCode());
    }

    public function testCannotCreateNewVersion(): void
    {
        $e = DataAbstractionLayerException::cannotCreateNewVersion('product', 'product-id');

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals('Cannot create new version. product by id product-id not found.', $e->getMessage());
        static::assertEquals(DataAbstractionLayerException::CANNOT_CREATE_NEW_VERSION, $e->getErrorCode());
    }

    public function testVersionMergeAlreadyLocked(): void
    {
        $e = DataAbstractionLayerException::versionMergeAlreadyLocked('version-id');

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals(DataAbstractionLayerException::VERSION_MERGE_ALREADY_LOCKED, $e->getErrorCode());
        static::assertEquals('Merging of version version-id is locked, as the merge is already running by another process.', $e->getMessage());
    }

    public function testExpectedArray(): void
    {
        $e = DataAbstractionLayerException::expectedArray('some/path/0');

        static::assertEquals('Expected data at some/path/0 to be an array.', $e->getMessage());
        static::assertEquals('some/path/0', $e->getParameters()['path']);
        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals('FRAMEWORK__WRITE_MALFORMED_INPUT', $e->getErrorCode());
    }

    public function testExpectedAssociativeArray(): void
    {
        $e = DataAbstractionLayerException::expectedAssociativeArray('some/path/0');

        static::assertEquals('Expected data at some/path/0 to be an associative array.', $e->getMessage());
        static::assertEquals('some/path/0', $e->getParameters()['path']);
        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals('FRAMEWORK__INVALID_WRITE_INPUT', $e->getErrorCode());
    }

    public function testDecodeHandledByHydrator(): void
    {
        Feature::skipTestIfInActive('v6.7.0.0', $this);

        $field = new ManyToManyAssociationField(
            'galleries',
            'MediaGallery',
            'MediaGalleryMapping',
            'media_id',
            'gallery_id',
        );

        $e = DataAbstractionLayerException::decodeHandledByHydrator($field);

        static::assertEquals(
            \sprintf('Decoding of %s is handled by the entity hydrator.', ManyToManyAssociationField::class),
            $e->getMessage()
        );
        static::assertEquals(ManyToManyAssociationField::class, $e->getParameters()['fieldClass']);
        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals(DataAbstractionLayerException::DECODE_HANDLED_BY_HYDRATOR, $e->getErrorCode());
    }

    #[DisabledFeatures(['v6.7.0.0'])]
    public function testFkFieldByStorageNameNotFound66(): void
    {
        $e = DataAbstractionLayerException::fkFieldByStorageNameNotFound(ProductDefinition::class, 'taxId');

        static::assertEquals(
            'Could not find FK field "taxId" from definition "Shopware\Core\Content\Product\ProductDefinition"',
            $e->getMessage()
        );
    }

    public function testFkFieldByStorageNameNotFound(): void
    {
        Feature::skipTestIfInActive('v6.7.0.0', $this);

        /** @var DataAbstractionLayerException $e */
        $e = DataAbstractionLayerException::fkFieldByStorageNameNotFound(ProductDefinition::class, 'taxId');

        static::assertEquals(
            'Can not detect FK field with storage name taxId in definition Shopware\Core\Content\Product\ProductDefinition',
            $e->getMessage()
        );

        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertEquals(DataAbstractionLayerException::REFERENCE_FIELD_BY_STORAGE_NAME_NOT_FOUND, $e->getErrorCode());
    }

    #[DisabledFeatures(['v6.7.0.0'])]
    public function testLanguageFieldByStorageNameNotFound66(): void
    {
        $e = DataAbstractionLayerException::languageFieldByStorageNameNotFound(ProductDefinition::class, 'taxId');

        static::assertEquals(
            'Could not find language field "taxId" in definition "Shopware\Core\Content\Product\ProductDefinition"',
            $e->getMessage()
        );
    }

    public function testLanguageFieldByStorageNameNotFound(): void
    {
        Feature::skipTestIfInActive('v6.7.0.0', $this);

        /** @var DataAbstractionLayerException $e */
        $e = DataAbstractionLayerException::languageFieldByStorageNameNotFound(ProductDefinition::class, 'taxId');

        static::assertEquals(
            'Can not detect language field with storage name taxId in definition Shopware\Core\Content\Product\ProductDefinition',
            $e->getMessage()
        );
        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertEquals(DataAbstractionLayerException::REFERENCE_FIELD_BY_STORAGE_NAME_NOT_FOUND, $e->getErrorCode());
    }

    #[DisabledFeatures(['v6.7.0.0'])]
    public function testDefinitionFieldDoesNotExist66(): void
    {
        $e = DataAbstractionLayerException::definitionFieldDoesNotExist(ProductDefinition::class, 'taxId');

        static::assertEquals(
            'Could not find reference field "taxId" from definition "Shopware\Core\Content\Product\ProductDefinition"',
            $e->getMessage()
        );
    }

    public function testDefinitionFieldDoesNotExist(): void
    {
        Feature::skipTestIfInActive('v6.7.0.0', $this);

        /** @var DataAbstractionLayerException $e */
        $e = DataAbstractionLayerException::definitionFieldDoesNotExist(ProductDefinition::class, 'taxId');

        static::assertEquals(
            'Can not detect reference field with storage name taxId in definition Shopware\Core\Content\Product\ProductDefinition',
            $e->getMessage()
        );
        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertEquals(DataAbstractionLayerException::REFERENCE_FIELD_BY_STORAGE_NAME_NOT_FOUND, $e->getErrorCode());
    }
}
