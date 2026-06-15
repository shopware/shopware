<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\ResolvedField;
use Shopware\Elasticsearch\TranslatedResolvedField;

/**
 * @internal
 */
#[CoversClass(ResolvedField::class)]
#[CoversClass(TranslatedResolvedField::class)]
#[Package('inventory')]
class ResolvedFieldTest extends TestCase
{
    public function testResolvedFieldGetters(): void
    {
        $stringField = new StringField('name', 'name');
        $resolved = new ResolvedField($stringField, 'tags');

        static::assertSame($stringField, $resolved->getResolvedField());
        static::assertSame('tags', $resolved->getRoot());
    }

    public function testResolvedFieldRootDefaultsToNull(): void
    {
        $stringField = new StringField('name', 'name');
        $resolved = new ResolvedField($stringField);

        static::assertNull($resolved->getRoot());
    }

    public function testTranslatedResolvedFieldGetters(): void
    {
        $stringField = new StringField('name', 'name');
        $translatedField = new TranslatedField('name');
        $resolved = new TranslatedResolvedField($stringField, $translatedField, 'categories');

        static::assertSame($stringField, $resolved->getResolvedField());
        static::assertSame($translatedField, $resolved->getTranslatedField());
        static::assertSame('categories', $resolved->getRoot());
    }

    public function testTranslatedResolvedFieldRootDefaultsToNull(): void
    {
        $stringField = new StringField('name', 'name');
        $translatedField = new TranslatedField('name');
        $resolved = new TranslatedResolvedField($stringField, $translatedField);

        static::assertNull($resolved->getRoot());
    }
}
