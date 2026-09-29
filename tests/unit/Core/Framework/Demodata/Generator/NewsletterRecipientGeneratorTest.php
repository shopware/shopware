<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataException;
use Shopware\Core\Framework\Demodata\Generator\NewsletterRecipientGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityWriter;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(NewsletterRecipientGenerator::class)]
class NewsletterRecipientGeneratorTest extends TestCase
{
    public function testGetDefinition(): void
    {
        $generator = new NewsletterRecipientGenerator(
            static::createStub(EntityWriterInterface::class),
            new NewsletterRecipientDefinition(),
            static::createStub(Connection::class),
        );

        static::assertSame(NewsletterRecipientDefinition::class, $generator->getDefinition());
    }

    public function testGenerateWithNoSalesChannelIds(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchFirstColumn')->willReturn([]);

        $this->expectExceptionObject(DemodataException::wrongExecutionOrder());
        (new NewsletterRecipientGenerator(
            static::createStub(EntityWriterInterface::class),
            new NewsletterRecipientDefinition(),
            $connection,
        ))->generate(
            1,
            static::createStub(DemodataContext::class),
        );
    }

    public function testGenerate(): void
    {
        $salesChannelId = Uuid::randomBytes();

        $entityWriter = new StaticEntityWriter();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchFirstColumn')->willReturn([$salesChannelId]);

        $generator = $this->createMock(Generator::class);
        $generator->expects($this->exactly(303))->method('format')->willReturnMap([
            ['safeEmail', [], 'test@example.com'],
            ['firstName', [], 'Jane'],
            ['lastName', [], 'Doe'],
        ]);

        $numberOfItems = 101;

        $progressAdvances = [];
        $console = $this->createMock(SymfonyStyle::class);
        $console->expects($this->once())->method('progressStart')->with($numberOfItems);
        $console->expects($this->exactly(2))->method('progressAdvance')
            ->willReturnCallback(
                static function (int $step) use (&$progressAdvances): void {
                    $progressAdvances[] = $step;
                },
            );
        $console->expects($this->once())->method('progressFinish');

        $context = new DemodataContext(
            Context::createDefaultContext(),
            $generator,
            __DIR__,
            $console,
            static::createStub(DefinitionInstanceRegistry::class),
        );

        (new NewsletterRecipientGenerator(
            $entityWriter,
            new NewsletterRecipientDefinition(),
            $connection,
        ))->generate(
            $numberOfItems,
            $context,
        );

        static::assertSame([100, 1], $progressAdvances);

        $upserts = $entityWriter->upserts;
        static::assertCount($numberOfItems, $upserts);
        foreach ($upserts as $upsert) {
            static::assertStringEndsWith('test@example.com', $upsert['email']);
            static::assertSame('Jane', $upsert['firstName']);
            static::assertSame('Doe', $upsert['lastName']);
            static::assertSame($salesChannelId, $upsert['salesChannelId']);
            static::assertTrue($upsert['customFields']['shopwareDemoData']);
        }
    }
}
