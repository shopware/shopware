<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(RenderingCacheContext::class)]
class RenderingCacheContextTest extends TestCase
{
    #[TestDox('returns false for isDisabled on construction')]
    public function testNewContextIsNotDisabled(): void
    {
        $context = new RenderingCacheContext();

        static::assertFalse($context->isDisabled());
    }

    #[TestDox('returns empty tags on construction')]
    public function testNewContextHasNoTags(): void
    {
        $context = new RenderingCacheContext();

        static::assertSame([], $context->getTags());
    }

    #[TestDox('accumulates tags across multiple calls and deduplicates within and across calls')]
    public function testAddTagsAccumulatesAndDeduplicatesTags(): void
    {
        $context = new RenderingCacheContext();

        $context->addTags(['tag-a', 'tag-b']);
        $context->addTags(['tag-b', 'tag-c']);

        static::assertSame(['tag-a', 'tag-b', 'tag-c'], $context->getTags());
    }

    #[TestDox('makes isDisabled return true irreversibly after calling disable')]
    public function testDisableMakesIsDisabledReturnTrueIrreversibly(): void
    {
        $context = new RenderingCacheContext();

        $context->disable();
        static::assertTrue($context->isDisabled());

        $context->disable();
        static::assertTrue($context->isDisabled());
    }
}
