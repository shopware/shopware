<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FrameworkException::class)]
class FrameworkExceptionTest extends TestCase
{
    public function testProjectDirNotExists(): void
    {
        $exception = FrameworkException::projectDirNotExists('test');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('FRAMEWORK__PROJECT_DIR_NOT_EXISTS', $exception->getErrorCode());
        static::assertSame('Project directory "test" does not exist.', $exception->getMessage());
        static::assertSame(['dir' => 'test'], $exception->getParameters());
    }

    public function testCollectionElementInvalidType(): void
    {
        $exception = FrameworkException::collectionElementInvalidType('foo', 'bar');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('FRAMEWORK__INVALID_COLLECTION_ELEMENT_TYPE', $exception->getErrorCode());
        static::assertSame('Expected collection element of type foo got bar', $exception->getMessage());
        static::assertSame(['expected' => 'foo', 'element' => 'bar'], $exception->getParameters());
    }

    public function testCreateFromError(): void
    {
        $exception = FrameworkException::createFromError('error message');

        static::assertInstanceOf(FrameworkException::class, $exception);
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('FRAMEWORK__CREATE_FROM_ERROR', $exception->getErrorCode());
        static::assertSame('error message', $exception->getMessage());
        static::assertSame([], $exception->getParameters());
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCreateFromErrorWithDeprecatedBehavior(): void
    {
        $exception = FrameworkException::createFromError('error message');

        static::assertEquals(new \InvalidArgumentException('error message'), $exception);
    }
}
