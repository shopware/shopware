<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Write;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
class ProductTypeImmutableTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    public function testUpdatingProductTypeToDifferentValueFails(): void
    {
        $id = Uuid::randomHex();
        $this->createProduct($id, 'default');

        $exception = new WriteException();

        $violationList = new ConstraintViolationList();
        $violationList->add(
            new ConstraintViolation(
                'The field "type" of "product" is immutable and cannot be updated.',
                'The field "type" of "product" is immutable and cannot be updated.',
                [
                    'field' => 'type',
                    'entity' => 'product',
                ],
                'special',
                'type',
                'not so special'
            )
        );

        $exception->add(new WriteConstraintViolationException($violationList));
        static::expectExceptionObject($exception);

        $this->getRepository()->update([
            ['id' => $id, 'type' => 'alternative'],
        ], Context::createDefaultContext());
    }

    public function testUpdatingProductTypeWithSameValueSucceeds(): void
    {
        $id = Uuid::randomHex();
        $this->createProduct($id, 'special');

        $postEventDispatched = false;

        $this->getContainer()->get('event_dispatcher')->addListener(PostWriteValidationEvent::class, function (PostWriteValidationEvent $event) use (&$postEventDispatched) {
            $postEventDispatched = true;

            self::assertCount(0, $event->getExceptions()->getExceptions());
        });

        $this->getRepository()->update([
            ['id' => $id, 'name' => 'Test product updated', 'stock' => 2, 'type' => 'special'],
        ], Context::createDefaultContext());

        static::assertTrue($postEventDispatched);

        $after = $this->getRepository()->search(new Criteria([$id]), Context::createDefaultContext())->get($id);

        static::assertInstanceOf(ProductEntity::class, $after);
        static::assertSame('special', $after->getType());
        static::assertSame(2, $after->getStock());
        static::assertSame('Test product updated', $after->getName());
    }

    public function testUpsertProductTypeManyTimesWithSameValueSucceeds(): void
    {
        $id = Uuid::randomHex();

        $event = $this->getRepository()->upsert([
            [
                'id' => $id,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test product',
                'type' => 'special',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 19],
            ],
        ], Context::createDefaultContext());

        static::assertCount(0, $event->getErrors());
        static::assertSame([
            $id,
        ], $event->getEventByEntityName(ProductDefinition::ENTITY_NAME)?->getIds());

        $event = $this->getRepository()->upsert([
            [
                'id' => $id,
                'name' => 'Test product updated',
                'stock' => 2,
                'type' => 'special', // same type as before
            ],
        ], Context::createDefaultContext());

        static::assertCount(0, $event->getErrors());
        static::assertSame([
            $id,
        ], $event->getEventByEntityName(ProductDefinition::ENTITY_NAME)?->getIds());

        $after = $this->getRepository()->search(new Criteria([$id]), Context::createDefaultContext())->get($id);

        static::assertInstanceOf(ProductEntity::class, $after);
        static::assertSame('special', $after->getType());
        static::assertSame(2, $after->getStock());
        static::assertSame('Test product updated', $after->getName());
    }

    public function testUpsertProductTypeManyTimesWithDifferentValueFailure(): void
    {
        $id = Uuid::randomHex();

        $event = $this->getRepository()->upsert([
            [
                'id' => $id,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test product',
                'type' => 'special',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 19],
            ],
        ], Context::createDefaultContext());

        static::assertCount(0, $event->getErrors());
        static::assertSame([
            $id,
        ], $event->getEventByEntityName(ProductDefinition::ENTITY_NAME)?->getIds());

        $exception = new WriteException();

        $violationList = new ConstraintViolationList();
        $violationList->add(
            new ConstraintViolation(
                'The field "type" of "product" is immutable and cannot be updated.',
                'The field "type" of "product" is immutable and cannot be updated.',
                [
                    'field' => 'type',
                    'entity' => 'product',
                ],
                'special',
                'type',
                'not so special'
            )
        );

        $exception->add(new WriteConstraintViolationException($violationList));
        static::expectExceptionObject($exception);

        $this->getRepository()->upsert([
            [
                'id' => $id,
                'name' => 'Test product updated',
                'type' => 'not so special', // different type than before
            ],
        ], Context::createDefaultContext());
    }

    private function createProduct(string $id, string $type): void
    {
        $this->getRepository()->create([
            [
                'id' => $id,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test product',
                'type' => $type,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 19],
            ],
        ], Context::createDefaultContext());
    }

    /**
     * @return EntityRepository<ProductCollection>
     */
    private function getRepository(): EntityRepository
    {
        /** @var EntityRepository<ProductCollection> $repo */
        $repo = static::getContainer()->get('product.repository');

        return $repo;
    }
}
