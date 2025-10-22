<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AbstractContextGatewayCommand::class)]
class AbstractContextGatewayCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = new class(['foo' => 'bar'], 'baz') extends AbstractContextGatewayCommand {
            /**
             * @param array<string, mixed> $array
             */
            public function __construct(
                public readonly array $array,
                public readonly string $string,
            ) {
            }

            public static function getDefaultKeyName(): string
            {
                return 'context_test-command';
            }
        };

        static::assertSame('context_test-command', $command::getDefaultKeyName());
        static::assertSame(['foo' => 'bar'], $command->array);
        static::assertSame('baz', $command->string);

        $command = $command::createFromPayload([
            'array' => ['wow' => 'ser'],
            'string' => 'foo',
        ]);

        static::assertSame('context_test-command', $command::getDefaultKeyName());
        static::assertObjectHasProperty('array', $command);
        static::assertObjectHasProperty('string', $command);
        static::assertSame(['wow' => 'ser'], $command->array);
        static::assertSame('foo', $command->string);
    }
}
