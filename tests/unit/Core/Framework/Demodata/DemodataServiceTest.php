<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Demodata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Demodata\DemodataRequest;
use Shopware\Core\Framework\Demodata\DemodataService;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DemodataService::class)]
class DemodataServiceTest extends TestCase
{
    #[TestDox('An empty request generates nothing and falls back to a null-output console')]
    public function testGeneratesNothingForEmptyRequestWithoutConsole(): void
    {
        $service = new DemodataService(
            new \ArrayObject([]),
            '/project',
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(ClockInterface::class)
        );

        $context = Context::createDefaultContext();

        $demodataContext = $service->generate(new DemodataRequest(), $context, null);

        static::assertSame($context, $demodataContext->getContext());
        static::assertSame([], $demodataContext->getTimings());
    }

    public function testGeneratesRequestedItemsAndRecordsTiming(): void
    {
        $definitionClass = CategoryDefinition::class;
        $definition = new CategoryDefinition();

        $generator = static::createMock(DemodataGeneratorInterface::class);
        $generator->expects($this->once())
            ->method('getDefinition')
            ->willReturn($definitionClass);
        $generator->expects($this->once())
            ->method('generate')
            ->with(2, static::isInstanceOf(DemodataContext::class), ['option' => true]);

        $registry = static::createMock(DefinitionInstanceRegistry::class);
        $registry->expects($this->once())
            ->method('get')
            ->with($definitionClass)
            ->willReturn($definition);

        $clock = static::createMock(ClockInterface::class);
        $clock->expects($this->exactly(2))
            ->method('now')
            ->willReturnOnConsecutiveCalls(
                new \DateTimeImmutable('2026-01-01 00:00:00.000000'),
                new \DateTimeImmutable('2026-01-01 00:00:00.250000'),
            );

        $console = static::createMock(SymfonyStyle::class);
        $console->expects($this->once())->method('section')->with('Generating 2 items for category');
        $console->expects($this->once())->method('note');

        $service = new DemodataService(
            new \ArrayObject([$generator]),
            '/project',
            $registry,
            $clock
        );

        $request = new DemodataRequest();
        $request->add($definitionClass, 2, ['option' => true]);

        $demodataContext = $service->generate($request, Context::createDefaultContext(), $console);

        static::assertSame([
            $definitionClass => [
                'definition' => 'category',
                'items' => 2,
                'time' => 0.25,
            ],
        ], $demodataContext->getTimings());
    }
}
