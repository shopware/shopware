<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(PageNotFoundException::class)]
class PageNotFoundExceptionTest extends TestCase
{
    #[DataProvider('exceptionDataProvider')]
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
    public static function exceptionDataProvider(): iterable
    {
        yield PageNotFoundException::ERROR_CODE => [
            'exception' => new PageNotFoundException('cmsPageId'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => PageNotFoundException::ERROR_CODE,
            'message' => 'Page with id "cmsPageId" was not found.',
        ];
    }
}
