<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @package content
 *
 * @covers \Shopware\Core\Content\Cms\CmsException
 */
class CmsExceptionTest extends TestCase
{
    /**
     * @dataProvider exceptionDataProvider
     */
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
    public function exceptionDataProvider(): iterable
    {
        yield CmsException::DELETION_OF_DEFAULT_CODE => [
            'exception' => CmsException::deletionOfDefault(['id1', 'id2', 'id3']),
            'statusCode' => Response::HTTP_CONFLICT,
            'errorCode' => CmsException::DELETION_OF_DEFAULT_CODE,
            'message' => 'The cms pages with ids "id1, id2, id3" are assigned as a default and therefore can not be deleted.',
        ];

        yield CmsException::OVERALL_DEFAULT_SYSTEM_CONFIG_DELETION_CODE => [
            'exception' => CmsException::overallDefaultSystemConfigDeletion('cmsPageId'),
            'statusCode' => Response::HTTP_CONFLICT,
            'errorCode' => CmsException::OVERALL_DEFAULT_SYSTEM_CONFIG_DELETION_CODE,
            'message' => 'The cms page with id "cmsPageId" is assigned as a default to all sales channels and therefore can not be deleted.',
        ];
    }
}
