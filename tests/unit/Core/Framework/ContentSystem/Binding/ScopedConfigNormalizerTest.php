<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\RootSourceConfigMap;
use Shopware\Core\Framework\ContentSystem\Binding\ScopedConfigNormalizer;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ScopedConfigNormalizer::class)]
class ScopedConfigNormalizerTest extends TestCase
{
    #[TestDox('returns a scalar value unchanged')]
    public function testScalarUnchanged(): void
    {
        static::assertSame('productId', ScopedConfigNormalizer::normalize('productId'));
        static::assertNull(ScopedConfigNormalizer::normalize(null));
        static::assertSame(42, ScopedConfigNormalizer::normalize(42));
    }

    #[TestDox('returns a tag-free array unchanged')]
    public function testTagFreeArrayUnchanged(): void
    {
        $data = ['property' => 'productId', 'associations' => ['manufacturer', 'cover']];

        static::assertSame($data, ScopedConfigNormalizer::normalize($data));
    }

    #[TestDox('converts a scoped tag into the plain marker wrapper')]
    public function testConvertsScopedTag(): void
    {
        $map = ['product' => 'productId', 'category' => 'categoryId'];

        static::assertSame(
            [RootSourceConfigMap::MARKER => $map],
            ScopedConfigNormalizer::normalize(new TaggedValue(RootSourceConfigMap::TAG, $map)),
        );
    }

    #[TestDox('converts scoped tags nested inside a resolvedBy config block')]
    public function testConvertsNestedScopedTags(): void
    {
        $data = [
            'resolvedBy' => [
                'breadcrumb' => [
                    'property' => new TaggedValue(RootSourceConfigMap::TAG, ['product' => '{{productId}}', 'category' => '{{categoryId}}']),
                    'type' => new TaggedValue(RootSourceConfigMap::TAG, ['product' => 'product', 'category' => 'category']),
                    'referrerCategoryProperty' => '{{referrerCategoryId}}',
                ],
            ],
        ];

        static::assertSame([
            'resolvedBy' => [
                'breadcrumb' => [
                    'property' => [RootSourceConfigMap::MARKER => ['product' => '{{productId}}', 'category' => '{{categoryId}}']],
                    'type' => [RootSourceConfigMap::MARKER => ['product' => 'product', 'category' => 'category']],
                    'referrerCategoryProperty' => '{{referrerCategoryId}}',
                ],
            ],
        ], ScopedConfigNormalizer::normalize($data));
    }

    #[TestDox('throws on an unsupported tag')]
    public function testThrowsOnUnsupportedTag(): void
    {
        $this->expectExceptionObject(new ParseException('Unsupported YAML tag "!other".'));

        ScopedConfigNormalizer::normalize(new TaggedValue('other', ['product' => 'x']));
    }

    #[TestDox('throws when the scoped tag does not wrap a map')]
    public function testThrowsWhenScopedTagDoesNotWrapMap(): void
    {
        $this->expectExceptionObject(new ParseException('The "!scoped" tag must wrap a map, got string.'));

        ScopedConfigNormalizer::normalize(new TaggedValue(RootSourceConfigMap::TAG, 'productId'));
    }

    #[TestDox('throws when the reserved marker key is authored literally instead of via the tag')]
    public function testThrowsOnLiterallyAuthoredMarkerKey(): void
    {
        $this->expectExceptionObject(new ParseException('"$scoped" is a reserved key; use the "!scoped" tag instead.'));

        ScopedConfigNormalizer::normalize([
            'resolvedBy' => [
                'breadcrumb' => [
                    'property' => [RootSourceConfigMap::MARKER => ['product' => 'productId']],
                ],
            ],
        ]);
    }
}
