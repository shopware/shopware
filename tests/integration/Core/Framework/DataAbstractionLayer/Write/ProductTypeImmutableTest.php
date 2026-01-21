<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Write;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ProductTypeImmutableTest extends TestCase
{
    use KernelTestBehaviour;

    public function testUpdatingProductTypeToDifferentValueFails(): void
    {
        $id = Uuid::randomHex();
        $this->createProduct($id, 'default');

        $this->expectException(WriteException::class);
        $this->expectExceptionMessage('The field "type" of "product" is immutable and cannot be updated.');

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
            ['id' => $id, 'type' => 'special'],
        ], Context::createDefaultContext());

        static::assertTrue($postEventDispatched);
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
