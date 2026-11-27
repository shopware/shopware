<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AttributeEntityDefinition::class)]
class AttributeEntityDefinitionTest extends TestCase
{
    public function testTranslatedFieldsTakeOverTheApiAwareFlag(): void
    {
        $definition = new AttributeEntityDefinition([
            'entity_name' => 'attribute_entity_definition_test',
            'fields' => [
                [
                    'name' => 'id',
                    'class' => IdField::class,
                    'flags' => [PrimaryKey::class => ['class' => PrimaryKey::class]],
                    'translated' => false,
                    'args' => ['id', 'id'],
                ],
                [
                    'name' => 'storeApiTitle',
                    'class' => StringField::class,
                    'flags' => [
                        ApiAware::class => ['class' => ApiAware::class, 'args' => [SalesChannelApiSource::class]],
                        AllowHtml::class => ['class' => AllowHtml::class, 'args' => ['sanitized' => true]],
                    ],
                    'translated' => true,
                    'args' => ['store_api_title', 'storeApiTitle'],
                ],
                [
                    'name' => 'title',
                    'class' => StringField::class,
                    'flags' => [ApiAware::class => ['class' => ApiAware::class, 'args' => []]],
                    'translated' => true,
                    'args' => ['title', 'title'],
                ],
                [
                    'name' => 'internalTitle',
                    'class' => StringField::class,
                    'flags' => [Required::class => ['class' => Required::class]],
                    'translated' => true,
                    'args' => ['internal_title', 'internalTitle'],
                ],
            ],
        ]);
        $definition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $storeApiTitle = $definition->getField('storeApiTitle');
        static::assertInstanceOf(TranslatedField::class, $storeApiTitle);
        $apiAware = $storeApiTitle->getFlag(ApiAware::class);
        static::assertInstanceOf(ApiAware::class, $apiAware);
        static::assertTrue($apiAware->isSourceAllowed(SalesChannelApiSource::class));
        static::assertFalse($apiAware->isSourceAllowed(AdminApiSource::class));
        static::assertFalse($storeApiTitle->is(AllowHtml::class));

        $title = $definition->getField('title');
        static::assertInstanceOf(TranslatedField::class, $title);
        $apiAware = $title->getFlag(ApiAware::class);
        static::assertInstanceOf(ApiAware::class, $apiAware);
        static::assertTrue($apiAware->isSourceAllowed(SalesChannelApiSource::class));
        static::assertTrue($apiAware->isSourceAllowed(AdminApiSource::class));

        $internalTitle = $definition->getField('internalTitle');
        static::assertInstanceOf(TranslatedField::class, $internalTitle);
        $apiAware = $internalTitle->getFlag(ApiAware::class);
        static::assertInstanceOf(ApiAware::class, $apiAware);
        static::assertFalse($apiAware->isSourceAllowed(SalesChannelApiSource::class));
        static::assertTrue($apiAware->isSourceAllowed(AdminApiSource::class));
        static::assertFalse($internalTitle->is(Required::class));
    }
}
