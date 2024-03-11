<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\MessageQueue\MessageHandlerCompilerPass;
use Shopware\Tests\Integration\Core\Framework\MessageQueue\fixtures\TestTask;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[CoversClass(MessageHandlerCompilerPass::class)]
class MessageHandlerCompilerPassTest extends TestCase
{
    /**
     * @param array<string, string> $existingTagAttributes
     * @param array<string, string> $expectedTagAttributes
     */
    #[DataProvider('tagProvider')]
    public function testAddsTagsAttributesFromAttribute(array $existingTagAttributes, array $expectedTagAttributes): void
    {
        $container = new ContainerBuilder();

        $container
            ->register(TestScheduledTaskHandler::class)
            ->setPublic(true)
            ->addTag('messenger.message_handler', $existingTagAttributes);

        $container->addCompilerPass(new MessageHandlerCompilerPass());

        $container->compile();

        static::assertTrue($container->has(TestScheduledTaskHandler::class));

        $handlerDefinition = $container->getDefinition(TestScheduledTaskHandler::class);

        $tagAttributes = $handlerDefinition->getTag('messenger.message_handler')[0];

        foreach ($expectedTagAttributes as $key => $value) {
            static::assertArrayHasKey($key, $tagAttributes);
            static::assertEquals($value, $tagAttributes[$key]);
        }
    }

    public static function tagProvider(): \Generator
    {
        yield 'noExistingTagAttributes' => [
            'existingTagAttributes' => [],
            'expectedTagAttributes' => [
                'bus' => 'testBus',
                'handles' => TestTask::class,
                'fromTransport' => 'testTransport',
                'method' => 'handleTestTask',
                'priority' => 10,
            ],
        ];

        yield 'existingTagAttributes' => [
            'existingTagAttributes' => [
                'method' => 'originalHandleTestTask',
                'priority' => 20,
            ],
            'expectedTagAttributes' => [
                'bus' => 'testBus',
                'handles' => TestTask::class,
                'fromTransport' => 'testTransport',
                'method' => 'originalHandleTestTask',
                'priority' => 20,
            ],
        ];
    }
}

/**
 * @internal
 */
#[AsMessageHandler(
    bus: 'testBus',
    fromTransport: 'testTransport',
    handles: TestTask::class,
    method: 'handleTestTask',
    priority: 10
)]
final class TestScheduledTaskHandler
{
}
