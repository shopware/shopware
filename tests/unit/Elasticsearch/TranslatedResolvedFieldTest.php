<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\TranslatedResolvedField;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(TranslatedResolvedField::class)]
class TranslatedResolvedFieldTest extends TestCase
{
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
