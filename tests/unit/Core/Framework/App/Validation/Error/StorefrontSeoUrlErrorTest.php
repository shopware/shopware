<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Validation\Error\StorefrontSeoUrlError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StorefrontSeoUrlError::class)]
class StorefrontSeoUrlErrorTest extends TestCase
{
    public function testMessageKey(): void
    {
        $error = new StorefrontSeoUrlError(['blog: an entity bound seo-url must not define a path']);

        static::assertSame('manifest-invalid-storefront-seo-url', $error->getMessageKey());
    }

    public function testMessageListsEveryViolation(): void
    {
        $error = new StorefrontSeoUrlError([
            'blog: an entity bound seo-url must not define a path',
            'imprint: the path "account" is already used by another route',
        ]);

        static::assertSame(
            "The following storefront SEO URLs are invalid:\n"
            . "- blog: an entity bound seo-url must not define a path\n"
            . '- imprint: the path "account" is already used by another route',
            $error->getMessage()
        );
    }

    public function testMessageWithSingleViolation(): void
    {
        $error = new StorefrontSeoUrlError(['blog: an entity bound seo-url requires a non-empty default-template']);

        static::assertSame(
            "The following storefront SEO URLs are invalid:\n- blog: an entity bound seo-url requires a non-empty default-template",
            $error->getMessage()
        );
    }
}
