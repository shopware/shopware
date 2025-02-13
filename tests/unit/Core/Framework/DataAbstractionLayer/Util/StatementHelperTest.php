<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Util;

use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Util\StatementHelper;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StatementHelper::class)]
class StatementHelperTest extends TestCase
{
    private Statement&MockObject $stmt;

    protected function setUp(): void
    {
        $this->stmt = $this->createMock(Statement::class);
    }

    public function testBindParameters(): void
    {
        $parameters = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];
        $this->expectBinds($parameters);
        StatementHelper::bindParameters($this->stmt, $parameters);
    }

    public function testExecuteStatement(): void
    {
        $parameters = [
            'id' => 1234,
            'name' => 'test',
        ];

        $this->expectBinds($parameters);
        $this->stmt->expects(static::once())
            ->method('executeStatement')
            ->willReturn(42);
        $result = StatementHelper::executeStatement($this->stmt, $parameters);
        static::assertSame(42, $result);
    }

    public function testExecuteStatementWithoutParameters(): void
    {
        $this->stmt->expects(static::once())
            ->method('executeStatement')
            ->willReturn(0);
        $this->stmt->expects(static::never())
            ->method('bindValue');

        $result = StatementHelper::executeStatement($this->stmt);
        static::assertSame(0, $result);
    }

    public function testExecuteQuery(): void
    {
        $parameters = [
            'id' => 1234,
            'name' => 'test',
        ];

        $expectedResult = $this->createMock(Result::class);
        $this->expectBinds($parameters);
        $this->stmt->expects(static::once())
            ->method('executeQuery')
            ->willReturn($expectedResult);
        $result = StatementHelper::executeQuery($this->stmt, $parameters);
        static::assertSame($expectedResult, $result);
    }

    /**
     * @param array<string,mixed> $parameters
     */
    private function expectBinds(array $parameters): void
    {
        $matcher = static::exactly(\count($parameters));
        $keys = array_keys($parameters);
        $this->stmt->expects($matcher)
            ->method('bindValue')
            ->willReturnCallback(function ($key, $value) use ($parameters, $keys, $matcher): void {
                $expectedKey = $keys[$matcher->numberOfInvocations() - 1];
                static::assertSame($expectedKey, $key);
                static::assertSame($parameters[$key], $value);
            });
    }
}
