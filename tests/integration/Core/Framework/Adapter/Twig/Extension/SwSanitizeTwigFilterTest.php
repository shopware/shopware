<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Framework\Adapter\Twig\Extension\SwSanitizeTwigFilter;

/**
 * @internal
 */
class SwSanitizeTwigFilterTest extends \Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestCase
{
    private SwSanitizeTwigFilter $swSanitize;

    protected function setUp(): void
    {
        $this->swSanitize = static::getContainer()->get(SwSanitizeTwigFilter::class);
    }

    public function testTwigFilterIsRegistered(): void
    {
        $filters = $this->swSanitize->getFilters();

        static::assertCount(1, $filters);
        static::assertSame('sw_sanitize', $filters[0]->getName());
    }
}
