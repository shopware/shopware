<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Util;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\HtmlSanitizer;

/**
 * @internal
 */
class HtmlSanitizerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private string $unfilteredString = '<div style="background-color:#0E75FB;">test</div>';

    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
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

        $config = $newPurifier->config;
        static::assertInstanceOf(\HTMLPurifier_Config::class, $config);
        static::assertNull($config->get('Cache.DefinitionImpl'));
        static::assertEquals($cacheDir, $config->get('Cache.SerializerPath'));
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

        static::assertSame('<img alt="" src="#" /><script type="text/javascript"></script><div>test</div>', $filteredString);

        $filteredString = $this->sanitizer->sanitize($unfilteredString, [], false, 'test.custom');

        static::assertSame('<input /><img alt="" src="#" /><div>test</div>', $filteredString);
    }

    public function testConfigHasRightCachePermissions(): void
    {
        $currentUmask = umask();
        umask(0002);

        $cacheDir = $this->getContainer()->getParameter('kernel.cache_dir');

        $sanitizer = new HtmlSanitizer(
            $cacheDir,
            true
        );

        $sanitizer->sanitize($this->unfilteredString);

        $reflObj = new \ReflectionObject($sanitizer);
        $reflProp = $reflObj->getProperty('purifiers');
        $reflProp->setAccessible(true);

        $purifiers = $reflProp->getValue($sanitizer);

        static::assertCount(1, $purifiers);

        /** @var \HTMLPurifier $newPurifier */
        $newPurifier = array_pop($purifiers);

        $expectedPermissions = 0775 & ~umask();

        $config = $newPurifier->config;
        static::assertInstanceOf(\HTMLPurifier_Config::class, $config);
        static::assertSame($expectedPermissions, $config->get('Cache.SerializerPermissions'));
        umask($currentUmask);
    }

    public function testAllowedBootstrapAttributes(): void
    {
        $unfilteredString = '<a href=\"%target%\" data-toggle=\"modal\" data-bs-toggle=\"modal\" data-target=\"%target%\" data-bs-target=\"%target%\">Klicken Sie hier</a> um alle Ihre persönlichen Daten zu löschen"';

        $filteredString = $this->sanitizer->sanitize($unfilteredString, null, false, 'snippet.value');

        static::assertSame('<a href="\&quot;%target%\&quot;" data-bs-toggle="\&quot;modal\&quot;" data-bs-target="\&quot;%target%\&quot;">Klicken Sie hier</a> um alle Ihre persönlichen Daten zu löschen"', $filteredString);

        $unfilteredString = '<a href=\"%target%\" data-bs-toggle=\"modal\" data-bs-non-exist="foo">Klicken Sie hier</a> um alle Ihre persönlichen Daten zu löschen"';
        $filteredString = $this->sanitizer->sanitize($unfilteredString, null, false, 'snippet.value');

        static::assertSame('<a href="\&quot;%target%\&quot;" data-bs-toggle="\&quot;modal\&quot;">Klicken Sie hier</a> um alle Ihre persönlichen Daten zu löschen"', $filteredString);
    }

    public function testAllowedImgInSnippetValue(): void
    {
        $filteredString = $this->sanitizer->sanitize('<img alt="" src="#" />', null, false, 'snippet.value');

        static::assertSame('<img alt="" src="#" />', $filteredString);
    }

    public function testAllowedTargetAndRelAttribute(): void
    {
        $filteredString = $this->sanitizer->sanitize('<a rel="noopener" target="_blank" href="#">Test</a>', null, false, 'snippet.value');

        static::assertSame('<a target="_blank" href="#" rel="noreferrer noopener">Test</a>', $filteredString);
    }
}
