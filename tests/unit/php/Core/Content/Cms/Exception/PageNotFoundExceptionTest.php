<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @package content
 * @covers \Shopware\Core\Content\Cms\Exception\PageNotFoundException
 */
class PageNotFoundExceptionTest extends TestCase
{
    /**
     * @dataProvider exceptionDataProvider
     */
    public function testItThrowsException(PageNotFoundException $exception, int $statusCode, string $errorCode, string $message): void
    {
        $exceptionWasThrown = false;

        try {
            throw $exception;
        } catch (PageNotFoundException $cmsException) {
            static::assertEquals($statusCode, $cmsException->getStatusCode());
            static::assertEquals($errorCode, $cmsException->getErrorCode());
            static::assertEquals($message, $cmsException->getMessage());

            $exceptionWasThrown = true;
        } finally {
            static::assertTrue($exceptionWasThrown, 'Excepted exception with error code ' . $errorCode . ' to be thrown.');
        }
    }

    /**
     * @return array<string, array{exception: PageNotFoundException, statusCode: int, errorCode: string, message: string}>
     */
    public function exceptionDataProvider(): iterable
    {
        yield PageNotFoundException::ERROR_CODE => [
            'exception' => new PageNotFoundException('cmsPageId'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => PageNotFoundException::ERROR_CODE,
            'message' => 'Page with id "cmsPageId" was not found.',
        ];
    }
}
