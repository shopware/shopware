<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * @internal
 */
class DisabledTwigCacheWarmupTest extends TestCase
{
    use KernelTestBehaviour;

    public function testServiceIsRemoved(): void
    {
        static::expectException(ServiceNotFoundException::class);
        $this->getContainer()->get('twig.template_cache_warmer');
    }
}
