<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Util;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\HtmlSanitizer;

class HtmlSanitizerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private $unfilteredString = '<div style="background-color:#0E75FB;">test</div>';

    private HtmlSanitizer $sanitizer;

    public function setUp(): void
    {
        $this->sanitizer = $this->getContainer()->get(HtmlSanitizer::class);
    }

    public function testWithoutConfigUses(): void
    {
        $filteredString = $this->sanitizer->sanitize($this->unfilteredString);

        static::assertEquals($this->unfilteredString, $filteredString);
    }

    public function testOverrideHasNoEffectToFutureCalls(): void
    {
        $filteredWithOverride = $this->sanitizer->sanitize($this->unfilteredString, ['h1' => ['style']], true);
        $filteredString = $this->sanitizer->sanitize($this->unfilteredString);

        static::assertSame($filteredWithOverride, 'test');
        static::assertEquals($this->unfilteredString, $filteredString);
    }

    public function testForbiddenElementAllowedAttribute(): void
    {
        $filteredString = $this->sanitizer->sanitize($this->unfilteredString, ['h1' => ['style']], true);

        static::assertSame($filteredString, 'test');
    }

    public function testAllowedElementForbiddenAttribute(): void
    {
        $filteredString = $this->sanitizer->sanitize($this->unfilteredString, ['div' => []], true);

        static::assertSame($filteredString, '<div>test</div>');
    }

    public function testForbiddenElementForbiddenAttribute(): void
    {
        $filteredString = $this->sanitizer->sanitize($this->unfilteredString, [], true);

        static::assertSame($filteredString, 'test');
    }

    public function testAllowedElementAllowedAttribute(): void
    {
        $filteredString = $this->sanitizer->sanitize($this->unfilteredString, ['div' => ['style']], true);

        static::assertSame($filteredString, $this->unfilteredString);
    }

    public function testIfCacheIsDisabled(): void
    {
        $cacheDir = $this->getContainer()->getParameter('kernel.cache_dir');

        $sanitizer = new HtmlSanitizer(
            $cacheDir,
            false
        );

        $sanitizer->sanitize($this->unfilteredString);

        $reflObj = new \ReflectionObject($sanitizer);
        $reflProp = $reflObj->getProperty('purifiers');
        $reflProp->setAccessible(true);

        $purifiers = $reflProp->getValue($sanitizer);

        static::assertCount(1, $purifiers);

        /** @var \HTMLPurifier $newPurifier */
        $newPurifier = array_pop($purifiers);

        static::assertNull($newPurifier->config->get('Cache.DefinitionImpl'));
        static::assertEquals($cacheDir, $newPurifier->config->get('Cache.SerializerPath'));
    }

    public function testSanitizeNotThrowingOnNull(): void
    {
        $filteredString = $this->sanitizer->sanitize($this->unfilteredString, null, true);
        static::assertSame($filteredString, 'test');
    }

    public function testAllowedByFieldSetConfig(): void
    {
        $unfilteredString = '<input /><img alt="" src="#" /><script type="text/javascript"></script><div>test</div>';

        $filteredString = $this->sanitizer->sanitize($unfilteredString, [], false, 'test.media');

        static::assertSame('<img alt="" src="#" /><div>test</div>', $filteredString);

        $filteredString = $this->sanitizer->sanitize($unfilteredString, [], false, 'test.script');

        static::assertSame('<script type="text/javascript"></script><div>test</div>', $filteredString);

        $filteredString = $this->sanitizer->sanitize($unfilteredString, [], false, 'test.custom');

        static::assertSame('<input /><div>test</div>', $filteredString);
    }
}
