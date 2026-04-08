<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class TwigEnvironmentCompilerPassTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testTwigServicesUsesOurImplementation(): void
    {
        static::assertInstanceOf(TwigEnvironment::class, static::getContainer()->get('twig'));

        static::assertSame(
            static::getContainer()->getParameter('kernel.cache_dir') . '/twig',
            static::getContainer()->getParameter('twig.cache')
        );
    }
}
