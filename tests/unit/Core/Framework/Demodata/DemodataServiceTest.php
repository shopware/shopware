<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Demodata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Demodata\DemodataRequest;
use Shopware\Core\Framework\Demodata\DemodataService;
use Shopware\Core\Framework\Log\Package;

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
}
