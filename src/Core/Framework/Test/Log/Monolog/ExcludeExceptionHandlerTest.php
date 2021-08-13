<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Log\Monolog;

use Monolog\Handler\FingersCrossedHandler;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Monolog\ExcludeExceptionHandler;

class ExcludeExceptionHandlerTest extends TestCase
{
    /**
     * @dataProvider cases
     */
    public function testHandler(array $record, array $excludeList, bool $shouldBePassed): void
    {
        $innerHandler = $this->createMock(FingersCrossedHandler::class);
        $innerHandler->expects($shouldBePassed ? static::once() : static::never())->method('handle')->willReturn(true);

        $handler = new ExcludeExceptionHandler(
            $innerHandler,
            $excludeList
        );

        $handler->handle($record);
    }

    public function cases(): iterable
    {
        // record, exclude list, should be passed
        yield 'empty record' => [
            [],
            [],
            true,
        ];

        yield 'exception without exclude list' => [
            [
                'context' => [
                    'exception' => new \RuntimeException(''),
                ],
            ],
            [],
            true,
        ];

        yield 'exception with exclude list that matches' => [
            [
                'context' => [
                    'exception' => new \RuntimeException(''),
                ],
            ],
            [
                \RuntimeException::class,
            ],
            false,
        ];

        yield 'exception with exclude list that not matches' => [
            [
                'context' => [
                    'exception' => new \InvalidArgumentException(''),
                ],
            ],
            [
                \RuntimeException::class,
            ],
            true,
        ];
    }
}
