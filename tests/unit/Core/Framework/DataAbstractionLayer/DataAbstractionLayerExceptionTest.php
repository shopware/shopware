<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidFilterQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\VersionMergeAlreadyLockedException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @covers \Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException
 *
 * @internal
 */
#[Package('core')]
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

    /**
     * @DisabledFeatures("v6.6.0.0")
     *
     * @deprecated tag:v6.6.0.0 - will be removed
     */
    public function testInvalidSerializerFieldLegacy(): void
    {
        $e = DataAbstractionLayerException::invalidSerializerField(FkField::class, new IdField('foo', 'foo'));

        static::assertInstanceOf(InvalidSerializerFieldException::class, $e);
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

    /**
     * @DisabledFeatures("v6.6.0.0")
     *
     * @deprecated tag:v6.6.0.0 - will be removed
     */
    public function testInvalidLanguageIdLegacy(): void
    {
        $e = DataAbstractionLayerException::invalidLanguageId('foo');

        static::assertInstanceOf(LanguageNotFoundException::class, $e);
    }

    public function testInvalidFilterQuery(): void
    {
        $e = DataAbstractionLayerException::invalidFilterQuery('foo', 'baz');

        static::assertInstanceOf(InvalidFilterQueryException::class, $e);
        static::assertEquals('foo', $e->getMessage());
        static::assertEquals('baz', $e->getPath());
    }

    public function testCannotCreateNewVersion(): void
    {
        $e = DataAbstractionLayerException::cannotCreateNewVersion('product', 'product-id');

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals('Cannot create new version. product by id product-id not found.', $e->getMessage());
        static::assertEquals(DataAbstractionLayerException::CANNOT_CREATE_NEW_VERSION, $e->getErrorCode());
    }

    /**
     * @DisabledFeatures("v6.6.0.0")
     *
     * @deprecated tag:v6.6.0.0 - will be removed
     */
    public function testVersionMergeAlreadyLockedLegacy(): void
    {
        $e = DataAbstractionLayerException::versionMergeAlreadyLocked('version-id');

        static::assertInstanceOf(VersionMergeAlreadyLockedException::class, $e);
    }

    public function testVersionMergeAlreadyLocked(): void
    {
        $e = DataAbstractionLayerException::versionMergeAlreadyLocked('version-id');

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals(DataAbstractionLayerException::VERSION_MERGE_ALREADY_LOCKED, $e->getErrorCode());
        static::assertEquals('Merging of version version-id is locked, as the merge is already running by another process.', $e->getMessage());
    }
}
