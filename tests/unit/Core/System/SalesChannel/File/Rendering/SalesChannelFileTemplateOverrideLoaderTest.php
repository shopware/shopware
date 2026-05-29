<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\File\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\File\Rendering\SalesChannelFileTemplateOverrideLoader;
use Twig\Error\LoaderError;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelFileTemplateOverrideLoader::class)]
class SalesChannelFileTemplateOverrideLoaderTest extends TestCase
{
    public function testTemplateOverridesAreOnlyVisibleInsideCallback(): void
    {
        $loader = new SalesChannelFileTemplateOverrideLoader();
        $templateName = '@Framework/files/agentic/llms.txt.twig';

        static::assertFalse($loader->exists($templateName));

        $source = $loader->withTemplateOverrides(
            [$templateName => 'override content'],
            static function () use ($loader, $templateName): string {
                static::assertTrue($loader->exists($templateName));

                return $loader->getSourceContext($templateName)->getCode();
            }
        );

        static::assertSame('override content', $source);
        static::assertFalse($loader->exists($templateName));
    }

    public function testNestedTemplateOverridesAreRestored(): void
    {
        $loader = new SalesChannelFileTemplateOverrideLoader();
        $templateName = '@Framework/files/agentic/llms.txt.twig';

        $source = $loader->withTemplateOverrides(
            [$templateName => 'outer content'],
            static fn (): string => $loader->withTemplateOverrides(
                [$templateName => 'inner content'],
                static fn (): string => $loader->getSourceContext($templateName)->getCode()
            ) . ' / ' . $loader->getSourceContext($templateName)->getCode()
        );

        static::assertSame('inner content / outer content', $source);
    }

    public function testCacheKeyContainsTemplateHash(): void
    {
        $loader = new SalesChannelFileTemplateOverrideLoader();
        $templateName = '@Framework/files/agentic/llms.txt.twig';

        $firstCacheKey = $loader->withTemplateOverrides(
            [$templateName => 'first content'],
            static fn (): string => $loader->getCacheKey($templateName)
        );
        $secondCacheKey = $loader->withTemplateOverrides(
            [$templateName => 'second content'],
            static fn (): string => $loader->getCacheKey($templateName)
        );

        static::assertNotSame($firstCacheKey, $secondCacheKey);
    }

    public function testUnknownTemplateThrowsLoaderError(): void
    {
        $loader = new SalesChannelFileTemplateOverrideLoader();

        static::expectException(LoaderError::class);

        $loader->getSourceContext('@Framework/files/agentic/llms.txt.twig');
    }

    public function testResetClearsTemplateOverrides(): void
    {
        $loader = new SalesChannelFileTemplateOverrideLoader();
        $templateName = '@Framework/files/agentic/llms.txt.twig';

        $loader->withTemplateOverrides(
            [$templateName => 'override content'],
            static function () use ($loader, $templateName): void {
                $loader->reset();

                static::assertFalse($loader->exists($templateName));
            }
        );
    }
}
