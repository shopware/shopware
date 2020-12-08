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

    public function testWithoutConfigUses(): void
    {
        $filteredString = $this->swSanitize->sanitize($this->unfilteredString);

        static::assertEquals($this->unfilteredString, $filteredString);
    }

    public function testOverrideHasNoEffectToFutureCalls(): void
    {
        $filteredWithOverride = $this->swSanitize->sanitize($this->unfilteredString, ['h1' => ['style']], true);
        $filteredString = $this->swSanitize->sanitize($this->unfilteredString);

        static::assertSame($filteredWithOverride, 'test');
        static::assertEquals($this->unfilteredString, $filteredString);
    }

    public function testForbiddenElementAllowedAttribute(): void
    {
        $filteredString = $this->swSanitize->sanitize($this->unfilteredString, ['h1' => ['style']], true);

        static::assertSame($filteredString, 'test');
    }

    public function testAllowedElementForbiddenAttribute(): void
    {
        $filteredString = $this->swSanitize->sanitize($this->unfilteredString, ['div' => []], true);

        static::assertSame($filteredString, '<div>test</div>');
    }

    public function testForbiddenElementForbiddenAttribute(): void
    {
        $filteredString = $this->swSanitize->sanitize($this->unfilteredString, [], true);

        static::assertSame($filteredString, 'test');
    }

    public function testAllowedElementAllowedAttribute(): void
    {
        $filteredString = $this->swSanitize->sanitize($this->unfilteredString, ['div' => ['style']], true);

        static::assertSame($filteredString, $this->unfilteredString);
    }

    public function testIfCacheIsDisabled(): void
    {
        $cacheDir = $this->getContainer()->getParameter('kernel.cache_dir');

        $swSanitize = new SwSanitizeTwigFilter(
            $cacheDir,
            false
        );

        $swSanitize->sanitize($this->unfilteredString);

        $reflObj = new \ReflectionObject($swSanitize);
        $reflProp = $reflObj->getProperty('purifiers');
        $reflProp->setAccessible(true);

        $purifiers = $reflProp->getValue($swSanitize);

        static::assertCount(1, $purifiers);

        /** @var HTMLPurifier $newPurifier */
        $newPurifier = array_pop($purifiers);

        static::assertNull($newPurifier->config->get('Cache.DefinitionImpl'));
        static::assertEquals($cacheDir, $newPurifier->config->get('Cache.SerializerPath'));
    }

    public function testSanitizeNotThrowingOnNull(): void
    {
        $filteredString = $this->swSanitize->sanitize($this->unfilteredString, null, true);
        static::assertSame($filteredString, 'test');
    }
}
