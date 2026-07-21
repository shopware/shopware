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
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityWriter;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
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
        $generator->expects($this->exactly(9))->method('format')->willReturnMap([
            ['safeEmail', [], 'test@example.com'],
            ['firstName', [], 'Jane'],
            ['lastName', [], 'Doe'],
        ]);

        $output = new BufferedOutput();
        $numberOfItems = 3;

        $context = new DemodataContext(
            Context::createDefaultContext(),
            $generator,
            __DIR__,
            new SymfonyStyle(new ArrayInput([]), $output),
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

        $cliOutput = $output->fetch();
        static::assertStringContainsString('3/3', $cliOutput);

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
