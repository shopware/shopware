<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\TwigEnvironmentCompilerPass;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[CoversClass(TwigEnvironmentCompilerPass::class)]
class TwigEnvironmentCompilerPassTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testTwigServicesUsesOurImplementation(): void
    {
        static::assertInstanceOf(TwigEnvironment::class, $this->getContainer()->get('twig'));

        static::assertSame(
            $this->getContainer()->getParameter('kernel.cache_dir') . '/twig',
            $this->getContainer()->getParameter('twig.cache')
        );
    }
}
