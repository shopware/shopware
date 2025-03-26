<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Snippet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Administration;
use Shopware\Administration\Snippet\SnippetFilesFinder;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Storefront\Storefront;

/**
 * @internal
 */
#[CoversClass(SnippetFilesFinder::class)]
class SnippetFilesFinderTest extends TestCase
{
    public function testSnippetFilesFinder(): void
    {
        $activePluginPaths = [
            'activePlugin',
            'invalidPlugin',
            'nonExistingPlugin',
        ];
        $pluginPaths = [
            'activePlugin',
            'irrelevantPlugin',
        ];
        $bundlePaths = [
            'Administration',
            'Storefront',
            'existingBundle',
            'nonExistingBundle',
            'activePlugin',
        ];

        $getBundleMockByPath = function (string $path): Plugin&MockObject {
            $plugin = $this->createMock(Plugin::class);
            $plugin
                ->method('getPath')
                ->willReturn(__DIR__ . '/fixtures/' . $path);

            return $plugin;
        };

        $plugins = array_map($getBundleMockByPath, $pluginPaths);
        $activePlugins = array_map($getBundleMockByPath, $activePluginPaths);

        $adminBundlePath = \dirname((string) ReflectionHelper::getFileName(Administration::class));
        $adminBundle = $this->createMock(Administration::class);
        $adminBundle
            ->method('getPath')
            ->willReturn($adminBundlePath);

        $property = ReflectionHelper::getProperty(Administration::class, 'name');
        $property->setValue($adminBundle, 'Administration');

        $storefrontBundle = $this->createMock(Storefront::class);
        $storefrontBundle
            ->method('getPath')
            ->willReturn(\dirname((string) ReflectionHelper::getFileName(Storefront::class)));

        $property = ReflectionHelper::getProperty(Storefront::class, 'name');
        $property->setValue($storefrontBundle, 'Storefront');

        $bundles = [
            ...array_map($getBundleMockByPath, $bundlePaths),
            ...$plugins,
            $adminBundle,
            $storefrontBundle,
        ];

        $subject = new SnippetFilesFinder($plugins, $activePlugins, $bundles);

        $files = $subject->findSnippetFiles('en-GB');

        static::assertContains($adminBundlePath . '/Resources/app/administration/src/app/snippet/en-GB.json', $files);
        static::assertContains(__DIR__ . '/fixtures/activePlugin/Resources/app/administration/src/en-GB.json', $files);
        static::assertContains(__DIR__ . '/fixtures/existingBundle/Resources/app/administration/src/en-GB.json', $files);
    }
}
