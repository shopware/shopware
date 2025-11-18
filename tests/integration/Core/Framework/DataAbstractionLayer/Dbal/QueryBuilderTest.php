<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(QueryBuilder::class)]
class QueryBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<ProductManufacturerCollection>
     */
    private EntityRepository $manufacturerRepository;

    private QueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $this->queryBuilder = new QueryBuilder(static::getContainer()->get(Connection::class));

        $this->manufacturerRepository = static::getContainer()->get('product_manufacturer.repository');
    }

    /**
     * @return array<non-empty-string, array<non-empty-string>>
     */
    public static function provideTitlesLookingLikeParameters(): array
    {
        return [
            'named parameter' => ['my :title'],
            'positional parameter' => ['my title ?'],
        ];
    }

    #[DataProvider('provideTitlesLookingLikeParameters')]
    public function testCriteriaTitleLookingLikeParameter(string $title): void
    {
        $id = $this->createManufacturer();

        $this->queryBuilder->select('LOWER(HEX(id))')
            ->from('product_manufacturer')
            ->where('product_manufacturer.id = UNHEX(:id)')
            ->setParameter('id', $id)
            ->setTitle($title);

        $result = $this->queryBuilder->executeQuery()->fetchOne();
        static::assertSame($id, $result);

        $sql = $this->queryBuilder->getSQL();
        $matches = [];
        preg_match('/-- (.+)\n/', $sql, $matches);
        static::assertSame($title, $matches[0]);
    }

    /**
     * @return non-falsy-string
     */
    private function createManufacturer(): string
    {
        $manufacturerId = Uuid::randomHex();

        $parameters = ['id' => $manufacturerId, 'name' => 'Test'];

        $this->manufacturerRepository->create([$parameters], Context::createDefaultContext());

        return $manufacturerId;
    }
}
