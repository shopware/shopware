<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow;

use Doctrine\DBAL\Driver\PDO\Exception as DbalPdoException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\TransactionFailedException;
use Shopware\Core\Content\Flow\FlowException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(FlowException::class)]
class FlowExceptionTest extends TestCase
{
    public function testMethodNotCompatible(): void
    {
        $e = FlowException::methodNotCompatible('myMethod', 'myClass');

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals(FlowException::METHOD_NOT_COMPATIBLE, $e->getErrorCode());
        static::assertEquals('Method myMethod is not compatible for myClass class', $e->getMessage());
    }

    /**
     * @return array<string, array{0: \Throwable, 1: string, 2: string}>
     */
    public static function exceptionProvider(): array
    {
        return [
            'commit-fail' => [
                new TableNotFoundException(
                    new DbalPdoException('Table not found', null, 1146),
                    null
                ),
                'Flow action transaction could not be committed and was rolled back. Exception: An exception occurred in the driver: Table not found',
                TransactionFailedException::FLOW_ACTION_TRANSACTION_COMMIT_FAILED,
            ],
            'action-aborted' => [
                TransactionFailedException::because(new \Exception('broken')),
                'Flow action transaction was aborted and rolled back. Exception: Transaction failed because an exception occurred. Exception: broken',
                TransactionFailedException::FLOW_ACTION_TRANSACTION_ABORTED,
            ],
            'uncaught-exception' => [
                new \Exception('broken'),
                'Flow action transaction could not be completed and was rolled back. An uncaught exception occurred: broken',
                TransactionFailedException::FLOW_ACTION_TRANSACTION_UNCAUGHT_EXCEPTION,
            ],
        ];
    }

    #[DataProvider('exceptionProvider')]
    public function testTransactionCommitFailed(\Throwable $previous, string $message, string $code): void
    {
        $e = FlowException::transactionFailed($previous);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals($code, $e->getErrorCode());
        static::assertEquals($message, $e->getMessage());
        static::assertSame($previous, $e->getPrevious());
    }
}
