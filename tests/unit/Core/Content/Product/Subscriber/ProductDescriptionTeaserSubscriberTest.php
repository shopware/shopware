<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductDescriptionTeaserBuilder;
use Shopware\Core\Content\Product\Subscriber\ProductDescriptionTeaserSubscriber;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Util\HtmlSanitizer;

/**
 * @internal
 */
#[CoversClass(ProductDescriptionTeaserSubscriber::class)]
class ProductDescriptionTeaserSubscriberTest extends TestCase
{
    public function testSubscribesToWriteEvent(): void
    {
        static::assertSame(
            [EntityWriteEvent::class => 'beforeWrite'],
            ProductDescriptionTeaserSubscriber::getSubscribedEvents()
        );
    }

    public function testStripsHtmlAndKeepsText(): void
    {
        $command = $this->createMock(WriteCommand::class);
        $command->method('hasField')->with('description')->willReturn(true);
        $command->method('getPayload')->willReturn([
            'description' => '<p style="color: red;">Hello <strong>World</strong></p>',
        ]);
        $command->expects($this->once())
            ->method('addPayload')
            ->with('description_teaser', 'Hello World');

        $this->dispatch($command);
    }

    public function testTruncatesToMaxLength(): void
    {
        $command = $this->createMock(WriteCommand::class);
        $command->method('hasField')->with('description')->willReturn(true);
        $command->method('getPayload')->willReturn([
            'description' => str_repeat('a', 1000),
        ]);
        $command->expects($this->once())
            ->method('addPayload')
            ->willReturnCallback(static function (string $field, ?string $value): void {
                static::assertSame('description_teaser', $field);
                static::assertNotNull($value);
                static::assertSame(512, mb_strlen($value));
            });

        $this->dispatch($command);
    }

    public function testKeepsNullDescription(): void
    {
        $command = $this->createMock(WriteCommand::class);
        $command->method('hasField')->with('description')->willReturn(true);
        $command->method('getPayload')->willReturn(['description' => null]);
        $command->expects($this->once())
            ->method('addPayload')
            ->with('description_teaser', null);

        $this->dispatch($command);
    }

    public function testIgnoresCommandsWithoutDescription(): void
    {
        $command = $this->createMock(WriteCommand::class);
        $command->method('hasField')->with('description')->willReturn(false);
        $command->expects($this->never())->method('addPayload');

        $this->dispatch($command);
    }

    private function dispatch(WriteCommand $command): void
    {
        $builder = new ProductDescriptionTeaserBuilder(
            new HtmlSanitizer(null, false, [], [ProductDescriptionTeaserBuilder::TEASER_FIELD => ['sets' => []]])
        );

        $event = $this->createMock(EntityWriteEvent::class);
        $event->expects($this->once())
            ->method('getCommandsForEntity')
            ->with(ProductTranslationDefinition::ENTITY_NAME)
            ->willReturn([$command]);

        (new ProductDescriptionTeaserSubscriber($builder))->beforeWrite($event);
    }
}
