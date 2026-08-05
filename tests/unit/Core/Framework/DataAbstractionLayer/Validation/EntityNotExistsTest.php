<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityNotExists;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityNotExists::class)]
class EntityNotExistsTest extends TestCase
{
    public function testConstructor(): void
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();

        $entityNotExists = new EntityNotExists(
            entity: 'product_review',
            context: $context,
            primaryProperty: 'customerId',
            criteria: $criteria,
        );

        static::assertSame('product_review', $entityNotExists->getEntity());
        static::assertSame($context, $entityNotExists->getContext());
        static::assertSame($criteria, $entityNotExists->getCriteria());
        static::assertSame('customerId', $entityNotExists->getPrimaryProperty());
        static::assertSame('The {{ entity }} entity already exists.', $entityNotExists->getMessage());
    }

    public function testConstructorWithCustomMessage(): void
    {
        $entityNotExists = new EntityNotExists(
            entity: 'product_review',
            context: Context::createDefaultContext(),
            message: 'The {{ entity }} was already reviewed by this customer.',
        );

        static::assertSame('The {{ entity }} was already reviewed by this customer.', $entityNotExists->getMessage());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConstructorWithoutCriteria(): void
    {
        $context = Context::createDefaultContext();

        $entityNotExists = new EntityNotExists(
            entity: 'product_review',
            context: $context,
            primaryProperty: 'customerId',
        );

        static::assertSame('product_review', $entityNotExists->getEntity());
        static::assertSame($context, $entityNotExists->getContext());
        static::assertEquals(new Criteria(), $entityNotExists->getCriteria());
        static::assertSame('customerId', $entityNotExists->getPrimaryProperty());
    }

    public function testConstructorWithoutPrimaryProperty(): void
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();

        $entityNotExists = new EntityNotExists(
            entity: 'product_review',
            context: $context,
            criteria: $criteria,
        );

        static::assertSame('product_review', $entityNotExists->getEntity());
        static::assertSame($context, $entityNotExists->getContext());
        static::assertSame($criteria, $entityNotExists->getCriteria());
        static::assertSame('id', $entityNotExists->getPrimaryProperty());
    }

    public function testConstructorWithoutPrimaryPropertyAndCriteria(): void
    {
        $context = Context::createDefaultContext();

        $entityNotExists = new EntityNotExists(
            entity: 'product_review',
            context: $context,
        );

        static::assertSame('product_review', $entityNotExists->getEntity());
        static::assertSame($context, $entityNotExists->getContext());
        static::assertSame('id', $entityNotExists->getPrimaryProperty());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConstructorWithoutEntity(): void
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();

        $this->expectExceptionObject(FrameworkException::missingOptions(\sprintf(
            'Option "entity" must be given for constraint %s',
            EntityNotExists::class
        )));

        new EntityNotExists(
            context: $context,
            primaryProperty: 'customerId',
            criteria: $criteria,
        );
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConstructorWithoutContext(): void
    {
        $criteria = new Criteria();

        $this->expectExceptionObject(FrameworkException::missingOptions(\sprintf(
            'Option "context" must be given for constraint %s',
            EntityNotExists::class
        )));

        new EntityNotExists(
            entity: 'product_review',
            primaryProperty: 'customerId',
            criteria: $criteria,
        );
    }

    /**
     * Ignore deprecation triggered by Symfony as the parent constructor is called
     */
    #[IgnoreDeprecations]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConstructorWithOptions(): void
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();

        $entityNotExists = new EntityNotExists([
            'entity' => 'product_review',
            'context' => $context,
            'criteria' => $criteria,
            'primaryProperty' => 'customerId',
        ]);

        static::assertSame('product_review', $entityNotExists->getEntity());
        static::assertSame($context, $entityNotExists->getContext());
        static::assertSame($criteria, $entityNotExists->getCriteria());
        static::assertSame('customerId', $entityNotExists->getPrimaryProperty());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConstructorWithOptionsWithoutEntity(): void
    {
        $context = Context::createDefaultContext();

        $this->expectExceptionObject(FrameworkException::missingOptions(\sprintf(
            'Option "entity" must be given for constraint %s',
            EntityNotExists::class
        )));

        new EntityNotExists([
            'context' => $context,
            'criteria' => new Criteria(),
            'primaryProperty' => 'customerId',
        ]);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConstructorWithOptionsWithoutContext(): void
    {
        $criteria = new Criteria();

        $this->expectExceptionObject(FrameworkException::missingOptions(\sprintf(
            'Option "context" must be given for constraint %s',
            EntityNotExists::class
        )));

        new EntityNotExists([
            'entity' => 'product_review',
            'criteria' => $criteria,
            'primaryProperty' => 'customerId',
        ]);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConstructorWithInvalidCriteria(): void
    {
        $context = Context::createDefaultContext();

        $this->expectExceptionObject(FrameworkException::invalidOptions(\sprintf(
            'Option "criteria" must be an instance of %s for constraint %s',
            Criteria::class,
            EntityNotExists::class
        )));

        /** @phpstan-ignore argument.type (for test purpose) */
        new EntityNotExists([
            'entity' => 'product_review',
            'context' => $context,
            'criteria' => 'invalid',
            'primaryProperty' => 'customerId',
        ]);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConstructorWithInvalidPrimaryProperty(): void
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();

        $this->expectExceptionObject(FrameworkException::invalidOptions(\sprintf(
            'Option "primaryProperty" must be a string for constraint %s',
            EntityNotExists::class
        )));

        /** @phpstan-ignore argument.type (for test purpose) */
        new EntityNotExists([
            'entity' => 'product_review',
            'context' => $context,
            'criteria' => $criteria,
            'primaryProperty' => 123,
        ]);
    }
}
