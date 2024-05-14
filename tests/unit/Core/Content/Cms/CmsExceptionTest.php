<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    #[DataProvider('exceptionDataProvider')]
    public function testItThrowsException(CmsException $exception, int $statusCode, string $errorCode, string $message): void
    {
        $exceptionWasThrown = false;

        try {
            throw $exception;
        } catch (CmsException $cmsException) {
            static::assertEquals($statusCode, $cmsException->getStatusCode());
            static::assertEquals($errorCode, $cmsException->getErrorCode());
            static::assertEquals($message, $cmsException->getMessage());

            $exceptionWasThrown = true;
        } finally {
            static::assertTrue($exceptionWasThrown, 'Excepted exception with error code ' . $errorCode . ' to be thrown.');
        }
    }

    /**
     * @return array<string, array{exception: CmsException, statusCode: int, errorCode: string, message: string}>
     */
    public static function exceptionDataProvider(): iterable
    {
        yield CmsException::DELETION_OF_DEFAULT_CODE => [
            'exception' => CmsException::deletionOfDefault(['id1', 'id2', 'id3']),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => CmsException::DELETION_OF_DEFAULT_CODE,
            'message' => 'The cms pages with ids "id1, id2, id3" are assigned as a default and therefore can not be deleted.',
        ];

        yield CmsException::OVERALL_DEFAULT_SYSTEM_CONFIG_DELETION_CODE => [
            'exception' => CmsException::overallDefaultSystemConfigDeletion('cmsPageId'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => CmsException::OVERALL_DEFAULT_SYSTEM_CONFIG_DELETION_CODE,
            'message' => 'The cms page with id "cmsPageId" is assigned as a default to all sales channels and therefore can not be deleted.',
        ];
    }
}
