<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Deprecated;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PromotionDefinition::class)]
class PromotionDefinitionTest extends TestCase
{
    public function testOrdersPerCustomerCountIsRemovedWithTheMajor(): void
    {
        static::assertNull($this->getDefinition()->getFields()->get('ordersPerCustomerCount'));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testOrdersPerCustomerCountIsDeprecated(): void
    {
        $field = $this->getDefinition()->getFields()->get('ordersPerCustomerCount');

        static::assertInstanceOf(JsonField::class, $field);
        static::assertTrue($field->is(Deprecated::class));
    }

    private function getDefinition(): PromotionDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [PromotionDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(PromotionDefinition::ENTITY_NAME);
        static::assertInstanceOf(PromotionDefinition::class, $definition);

        return $definition;
    }
}
