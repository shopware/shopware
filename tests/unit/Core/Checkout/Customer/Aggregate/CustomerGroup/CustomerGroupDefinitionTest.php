<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Aggregate\CustomerGroup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Context\ExecutionContextFactory;
use Symfony\Component\Validator\Mapping\Factory\BlackHoleMetadataFactory;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CustomerGroupDefinition::class)]
class CustomerGroupDefinitionTest extends TestCase
{
    public function testPriceBasisAcceptsTheNetBasis(): void
    {
        static::assertSame(
            ['price_basis' => CustomerGroupEntity::PRICE_BASIS_NET],
            $this->writePriceBasis(CustomerGroupEntity::PRICE_BASIS_NET)
        );
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testPriceBasisAcceptsTheNetBasisBeforeTheMajor(): void
    {
        static::assertSame(
            ['price_basis' => CustomerGroupEntity::PRICE_BASIS_NET],
            $this->writePriceBasis(CustomerGroupEntity::PRICE_BASIS_NET)
        );
    }

    public function testPriceBasisAcceptsTheGrossBasis(): void
    {
        static::assertSame(
            ['price_basis' => CustomerGroupEntity::PRICE_BASIS_GROSS],
            $this->writePriceBasis(CustomerGroupEntity::PRICE_BASIS_GROSS)
        );
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testPriceBasisRejectsTheGrossBasisBeforeTheMajor(): void
    {
        try {
            $this->writePriceBasis(CustomerGroupEntity::PRICE_BASIS_GROSS);
            static::fail('The gross price basis must be rejected until the v6.8.0.0 major is active.');
        } catch (WriteConstraintViolationException $exception) {
            $violation = $exception->getViolations()->get(0);

            static::assertSame('/priceBasis', $violation->getPropertyPath());
            static::assertSame(Choice::NO_SUCH_CHOICE_ERROR, $violation->getCode());
        }
    }

    public function testPriceBasisRejectsAnUnknownBasis(): void
    {
        try {
            $this->writePriceBasis('exact-net');
            static::fail('A price basis outside the defined choices must be rejected.');
        } catch (WriteConstraintViolationException $exception) {
            $violation = $exception->getViolations()->get(0);

            static::assertSame('/priceBasis', $violation->getPropertyPath());
            static::assertSame(Choice::NO_SUCH_CHOICE_ERROR, $violation->getCode());
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function writePriceBasis(string $priceBasis): array
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [CustomerGroupDefinition::class],
            new RecursiveValidator(
                new ExecutionContextFactory(static::createStub(TranslatorInterface::class)),
                new BlackHoleMetadataFactory(),
                new ConstraintValidatorFactory()
            ),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(CustomerGroupDefinition::ENTITY_NAME);
        static::assertInstanceOf(CustomerGroupDefinition::class, $definition);

        $field = $definition->getFields()->get('priceBasis');
        static::assertInstanceOf(StringField::class, $field);

        return iterator_to_array($field->getSerializer()->encode(
            $field,
            EntityExistence::createEmpty(),
            new KeyValuePair('priceBasis', $priceBasis, true),
            new WriteParameterBag(
                $definition,
                WriteContext::createFromContext(Context::createDefaultContext()),
                '',
                new WriteCommandQueue()
            )
        ));
    }
}
