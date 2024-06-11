<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(CmsException::class)]
class CmsExceptionTest extends TestCase
{
    public function testDeletionOfDefault(): void
    {
        $exception = CmsException::deletionOfDefault(['id1', 'id2', 'id3']);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(CmsException::DELETION_OF_DEFAULT_CODE, $exception->getErrorCode());
        static::assertSame('The cms pages with ids "id1, id2, id3" are assigned as a default and therefore can not be deleted.', $exception->getMessage());
        static::assertSame(['pages' => 'id1, id2, id3'], $exception->getParameters());
    }

    public function testOverallDefaultSystemConfigDeletion(): void
    {
        $exception = CmsException::overallDefaultSystemConfigDeletion('cmsPageId');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(CmsException::OVERALL_DEFAULT_SYSTEM_CONFIG_DELETION_CODE, $exception->getErrorCode());
        static::assertSame('The cms page with id "cmsPageId" is assigned as a default to all sales channels and therefore can not be deleted.', $exception->getMessage());
        static::assertSame(['cmsPageId' => 'cmsPageId'], $exception->getParameters());
    }
}
