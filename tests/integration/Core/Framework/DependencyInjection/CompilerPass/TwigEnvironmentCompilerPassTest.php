<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\TwigEnvironmentCompilerPass;

/**
 * @internal
 */
#[CoversClass(TwigEnvironmentCompilerPass::class)]
class TwigEnvironmentCompilerPassTest extends \Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestCase
{
    public function testTwigServicesUsesOurImplementation(): void
    {
        static::assertInstanceOf(TwigEnvironment::class, static::getContainer()->get('twig'));

        static::assertSame(
            static::getContainer()->getParameter('kernel.cache_dir') . '/twig',
            static::getContainer()->getParameter('twig.cache')
        );
    }
}
