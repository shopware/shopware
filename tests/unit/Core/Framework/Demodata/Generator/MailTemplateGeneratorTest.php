<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Demodata\Generator;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\Generator\MailTemplateGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityWriter;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(MailTemplateGenerator::class)]
class MailTemplateGeneratorTest extends TestCase
{
    public function testGetDefinition(): void
    {
        $generator = new MailTemplateGenerator(
            new StaticEntityWriter(),
            StaticEntityRepository::of(MailTemplateTypeCollection::class),
            new MailTemplateDefinition(),
        );

        static::assertSame(MailTemplateDefinition::class, $generator->getDefinition());
    }

    public function testGenerateCreatesOneTemplatePerTypeWithoutTemplate(): void
    {
        $typeIds = array_map(static fn () => Uuid::randomHex(), range(1, 11));

        $mailTemplateTypeRepository = StaticEntityRepository::of(MailTemplateTypeCollection::class, [$typeIds]);

        $entityWriter = new StaticEntityWriter();

        $console = $this->createMock(SymfonyStyle::class);
        $console->expects($this->once())->method('progressStart')->with(11);
        $console->expects($this->once())->method('progressAdvance')->with(10);
        $console->expects($this->once())->method('progressFinish');

        $context = new DemodataContext(
            Context::createDefaultContext(),
            Factory::create(),
            __DIR__,
            $console,
            static::createStub(DefinitionInstanceRegistry::class),
        );

        (new MailTemplateGenerator(
            $entityWriter,
            $mailTemplateTypeRepository,
            new MailTemplateDefinition(),
        ))->generate(11, $context);

        static::assertEquals(
            [new EqualsFilter('mail_template_type.mailTemplates.id', null)],
            $mailTemplateTypeRepository->searchedCriteria[0]->getFilters(),
        );
        static::assertSame($typeIds, array_column($entityWriter->upserts, 'mailTemplateTypeId'));
        foreach ($entityWriter->upserts as $upsert) {
            static::assertFalse($upsert['isSystemDefault']);
            static::assertTrue($upsert['customFields']['shopwareDemoData']);
        }
    }
}
