<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Choice;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StrictChoiceResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class StrictChoiceResolverTest extends TestCase
{
    use KernelTestBehaviour;

    public function testUsesTheRegisteredEnumProviders(): void
    {
        $resolver = static::getContainer()->get(StrictChoiceResolver::class);

        // ProductTypeRegistry is the tagged enum provider for product.type
        $field = (new StringField('type', 'type'))->addFlags(new Choice(['base'], strict: true));

        $choices = $resolver->getStrictChoices($field, ProductDefinition::ENTITY_NAME);

        static::assertIsArray($choices);
        static::assertContains('base', $choices);
        static::assertContains(ProductDefinition::TYPE_PHYSICAL, $choices);
        static::assertContains(ProductDefinition::TYPE_DIGITAL, $choices);
    }
}
