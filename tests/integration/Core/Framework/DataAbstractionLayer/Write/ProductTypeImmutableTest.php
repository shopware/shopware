<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Write;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;

/**
 * @internal
 */
class ProductTypeImmutableTest extends TestCase
{
    use IntegrationTestBehaviour;

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
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $id = Uuid::randomHex();
        $this->createProduct($id, 'special');

        $this->getRepository()->update([
            ['id' => $id, 'type' => 'special'],
        ], Context::createDefaultContext());

        static::assertTrue(true, 'No exception when setting the same type again.');
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

    private function getRepository(): EntityRepository
    {
        /** @var EntityRepository $repo */
        $repo = static::getContainer()->get('product.repository');

        return $repo;
    }
}
