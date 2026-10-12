<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Script\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Exception\ScriptExecutionFailedException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ScriptExecutionFailedException::class)]
class ScriptExecutionFailedExceptionTest extends TestCase
{
    #[TestDox('adopts status and error code from a shopware root exception')]
    public function testAdoptsRootExceptionData(): void
    {
        $root = CartException::cartLocked('token');
        $previous = new \RuntimeException('wrapped', 0, $root);

        $exception = new ScriptExecutionFailedException('cart', 'my-script.twig', $previous);

        static::assertSame($root->getStatusCode(), $exception->getStatusCode());
        static::assertSame($root->getErrorCode(), $exception->getErrorCode());
    }

    #[TestDox('falls back to 500 and its own error code without a shopware root exception')]
    public function testFallsBackWithoutRoot(): void
    {
        $exception = new ScriptExecutionFailedException('cart', 'my-script.twig', new \RuntimeException('plain'));

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(ScriptExecutionFailedException::ERROR_CODE, $exception->getErrorCode());
    }
}
