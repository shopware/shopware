<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(QueryBuilder::class)]
class QueryBuilderTest extends TestCase
{
    use KernelTestBehaviour;

    private QueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $this->queryBuilder = new QueryBuilder(self::getContainer()->get(Connection::class));
    }

    /**
     * @return array<non-empty-string, list<non-empty-string>>
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
        $this->queryBuilder->select('LOWER(HEX(id))')
            ->from('product_manufacturer')
            ->where('product_manufacturer.id = UNHEX(:id)')
            ->setParameter('id', Uuid::randomHex())
            ->setTitle($title);

        $this->queryBuilder->executeQuery()->fetchOne();

        $sql = $this->queryBuilder->getSQL();
        $matches = [];
        preg_match('/-- (.+)\n/', $sql, $matches);
        static::assertArrayHasKey(1, $matches);
        static::assertSame($title, $matches[1]);
    }

    public function testCriteriaTitleWithLineBreaksStaysInTheSqlComment(): void
    {
        $this->queryBuilder->select('id')
            ->from('product_manufacturer')
            ->setTitle("first\r\nsecond");

        $sql = $this->queryBuilder->getSQL();

        static::assertStringStartsWith('-- first  second' . \PHP_EOL, $sql);
        static::assertSame(1, substr_count($sql, \PHP_EOL));
    }
}
