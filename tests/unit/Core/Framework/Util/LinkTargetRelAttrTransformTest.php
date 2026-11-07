<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\LinkTargetRelAttrTransform;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LinkTargetRelAttrTransform::class)]
class LinkTargetRelAttrTransformTest extends TestCase
{
    /**
     * @param array<string, string> $attributes
     * @param array<string, string> $expected
     */
    #[DataProvider('attributeProvider')]
    public function testTransform(array $attributes, array $expected): void
    {
        $transform = new LinkTargetRelAttrTransform(['noreferrer', 'noopener']);

        static::assertSame(
            $expected,
            $transform->transform($attributes, \HTMLPurifier_Config::createDefault(), new \HTMLPurifier_Context())
        );
    }

    public function testAddsOnlyTheGivenRels(): void
    {
        $transform = new LinkTargetRelAttrTransform(['noopener']);

        static::assertSame(
            ['href' => '/detail', 'target' => '_blank', 'rel' => 'noopener'],
            $transform->transform(['href' => '/detail', 'target' => '_blank'], \HTMLPurifier_Config::createDefault(), new \HTMLPurifier_Context())
        );
    }

    public static function attributeProvider(): \Generator
    {
        yield 'a link without target is unchanged' => [
            ['href' => '/detail'],
            ['href' => '/detail'],
        ];

        yield 'an empty target is unchanged' => [
            ['href' => '/detail', 'target' => ''],
            ['href' => '/detail', 'target' => ''],
        ];

        yield 'a same-window target is unchanged' => [
            ['href' => '/detail', 'target' => '_self'],
            ['href' => '/detail', 'target' => '_self'],
        ];

        yield 'same-window targets are matched case-insensitively' => [
            ['href' => '/detail', 'target' => '_SELF'],
            ['href' => '/detail', 'target' => '_SELF'],
        ];

        yield 'a parent frame target is unchanged' => [
            ['href' => '/detail', 'target' => '_parent'],
            ['href' => '/detail', 'target' => '_parent'],
        ];

        yield 'a top frame target is unchanged' => [
            ['href' => '/detail', 'target' => '_top'],
            ['href' => '/detail', 'target' => '_top'],
        ];

        yield 'a new tab gets noreferrer and noopener' => [
            ['href' => '/detail', 'target' => '_blank'],
            ['href' => '/detail', 'target' => '_blank', 'rel' => 'noreferrer noopener'],
        ];

        yield 'a named window may be new, so it gets noreferrer and noopener' => [
            ['href' => '/detail', 'target' => 'preview'],
            ['href' => '/detail', 'target' => 'preview', 'rel' => 'noreferrer noopener'],
        ];

        yield 'existing rels are kept and whitespace is normalized' => [
            ['href' => '/detail', 'target' => '_blank', 'rel' => ' nofollow  print '],
            ['href' => '/detail', 'target' => '_blank', 'rel' => 'nofollow print noreferrer noopener'],
        ];

        yield 'rels that are already present are not duplicated' => [
            ['href' => '/detail', 'target' => '_blank', 'rel' => 'noopener'],
            ['href' => '/detail', 'target' => '_blank', 'rel' => 'noopener noreferrer'],
        ];
    }
}
