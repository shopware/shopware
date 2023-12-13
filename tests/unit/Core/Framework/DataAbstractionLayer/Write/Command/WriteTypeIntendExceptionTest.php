<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Write\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteTypeIntendException;
use Shopware\Core\Framework\Test\Api\ApiDefinition\EntityDefinition\SimpleDefinition;

/**
 * @internal
 */
#[CoversClass(WriteTypeIntendException::class)]
class WriteTypeIntendExceptionTest extends TestCase
{
    public function testErrorSignalsBadRequest(): void
    {
        $exception = new WriteTypeIntendException(
            new SimpleDefinition(),
            'expected',
            'actual'
        );

        static::assertSame(400, $exception->getStatusCode());
    }

    public function testDoesHintAtCorrectApiUsage(): void
    {
        $exception = new WriteTypeIntendException(
            new SimpleDefinition(),
            UpdateCommand::class,
            InsertCommand::class
        );

        static::assertStringContainsString('Use POST method', $exception->getMessage());
    }
}
