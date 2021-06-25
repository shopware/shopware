<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\Twig\Extension\SwSanitizeTwigFilter;

class SwSanitizeTwigFilterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private $unfilteredString = '<div style="background-color:#0E75FB;">test</div>';

    /**
     * @var SwSanitizeTwigFilter
     */
    private $swSanitize;

    public function setUp(): void
    {
        $this->swSanitize = $this->getContainer()->get(SwSanitizeTwigFilter::class);
    }

    public function testTwigFilterIsRegistered(): void
    {
        $filters = $this->swSanitize->getFilters();

        static::assertCount(1, $filters);
        static::assertEquals('sw_sanitize', $filters[0]->getName());
    }
}
