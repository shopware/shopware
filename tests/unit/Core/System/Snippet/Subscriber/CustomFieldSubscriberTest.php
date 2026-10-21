<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Snippet\Subscriber\CustomFieldSubscriber;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CustomFieldSubscriber::class)]
class CustomFieldSubscriberTest extends TestCase
{
    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('usableLabelsProvider')]
    public function testLoadsSnippetSetsForUsableLabel(array $payload): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllAssociative')->willReturn([]);

        $this->write($connection, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('unusableLabelsProvider')]
    public function testSkipsWriteWithoutUsableLabel(array $payload): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');

        $this->write($connection, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('scalarLabelsProvider')]
    public function testScalarLabelInsertsNameFallbackSnippetForEachSnippetSet(array $payload): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['id' => 'set-en', 'iso' => 'en-GB'],
            ['id' => 'set-de', 'iso' => 'de-DE'],
        ]);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $closure): mixed => $closure($connection));

        $values = [];
        $connection->expects($this->once())
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params) use (&$values): int {
                $values = $params;

                return 1;
            });

        $this->write($connection, $payload);

        // one row of seven columns per snippet set
        $rows = array_chunk($values, 7);
        static::assertSame(['set-en', 'set-de'], array_column($rows, 1));
        static::assertSame(['customFields.field', 'customFields.field'], array_column($rows, 2));
        static::assertSame(['field', 'field'], array_column($rows, 3));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function usableLabelsProvider(): iterable
    {
        yield 'label map' => [['name' => 'field', 'config' => ['label' => ['en-GB' => 'Label']]]];
        yield 'label map with empty text' => [['name' => 'field', 'config' => ['label' => ['en-GB' => '']]]];
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unusableLabelsProvider(): iterable
    {
        yield 'config without label' => [['name' => 'field', 'config' => ['type' => 'text']]];
        yield 'config empty array' => [['name' => 'field', 'config' => []]];
        yield 'config null' => [['name' => 'field', 'config' => null]];
        yield 'config string' => [['name' => 'field', 'config' => 'text']];
        yield 'config int' => [['name' => 'field', 'config' => 5]];
        yield 'label null' => [['name' => 'field', 'config' => ['label' => null]]];
        yield 'label empty string' => [['name' => 'field', 'config' => ['label' => '']]];
        yield 'label empty array' => [['name' => 'field', 'config' => ['label' => []]]];
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function scalarLabelsProvider(): iterable
    {
        yield 'label non-empty string' => [['name' => 'field', 'config' => ['label' => 'Label']]];
        yield 'label zero string' => [['name' => 'field', 'config' => ['label' => '0']]];
        yield 'label int' => [['name' => 'field', 'config' => ['label' => 1]]];
        yield 'label int zero' => [['name' => 'field', 'config' => ['label' => 0]]];
        yield 'label float' => [['name' => 'field', 'config' => ['label' => 1.5]]];
        yield 'label float zero' => [['name' => 'field', 'config' => ['label' => 0.0]]];
        yield 'label true' => [['name' => 'field', 'config' => ['label' => true]]];
        yield 'label false' => [['name' => 'field', 'config' => ['label' => false]]];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function write(Connection $connection, array $payload): void
    {
        $subscriber = new CustomFieldSubscriber($connection, new MockClock(new \DateTimeImmutable('2026-04-15 12:00:00')));

        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $subscriber->customFieldIsWritten(new EntityWrittenEvent(
                'custom_field',
                [new EntityWriteResult(Uuid::randomHex(), $payload, 'custom_field', EntityWriteResult::OPERATION_INSERT)],
                Context::createDefaultContext()
            ));
        } finally {
            restore_error_handler();
        }
    }
}
