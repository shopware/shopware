<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\EntitySearchTool;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class EntitySearchToolIntegrationTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private EntitySearchTool $tool;

    protected function setUp(): void
    {
        $registry = static::getContainer()->get(DefinitionInstanceRegistry::class);

        /** @var RequestCriteriaBuilder $criteriaBuilder */
        $criteriaBuilder = static::getContainer()->get(RequestCriteriaBuilder::class);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $this->tool = new EntitySearchTool($registry, $criteriaBuilder, $contextProvider);
    }

    public function testSearchCurrencyReturnsResults(): void
    {
        $output = ($this->tool)('currency');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertGreaterThan(0, $data['total']);
        static::assertNotEmpty($data['data']);
        static::assertArrayHasKey('_meta', $data);
        static::assertSame(1, $data['_meta']['page']);
    }

    public function testSearchWithFilter(): void
    {
        $output = ($this->tool)('currency', json_encode([
            'filter' => [
                ['type' => 'equals', 'field' => 'isoCode', 'value' => 'EUR'],
            ],
        ], \JSON_THROW_ON_ERROR));

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $data['total']);
        static::assertSame('EUR', $data['data'][0]['isoCode']);
    }

    public function testSearchWithPagination(): void
    {
        $output = ($this->tool)('currency', json_encode([
            'limit' => 1,
            'page' => 1,
        ], \JSON_THROW_ON_ERROR));

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $data['_meta']['limit']);
        static::assertSame(1, $data['_meta']['page']);
        static::assertCount(1, $data['data']);
    }

    public function testSearchWithSorting(): void
    {
        $output = ($this->tool)('currency', json_encode([
            'sort' => [
                ['field' => 'isoCode', 'order' => 'ASC'],
            ],
        ], \JSON_THROW_ON_ERROR));

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertGreaterThan(0, $data['total']);

        $isoCodes = array_column($data['data'], 'isoCode');
        $sorted = $isoCodes;
        sort($sorted);
        static::assertSame($sorted, $isoCodes);
    }

    public function testSearchLanguageWithAssociation(): void
    {
        $output = ($this->tool)('language', json_encode([
            'filter' => [
                ['type' => 'equals', 'field' => 'id', 'value' => Defaults::LANGUAGE_SYSTEM],
            ],
            'associations' => [
                'locale' => [],
            ],
        ], \JSON_THROW_ON_ERROR));

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $data['total']);
        static::assertArrayHasKey('locale', $data['data'][0]);
        static::assertNotNull($data['data'][0]['locale']);
    }

    public function testSearchReturnsEmptyForNonExistentFilter(): void
    {
        $output = ($this->tool)('currency', json_encode([
            'filter' => [
                ['type' => 'equals', 'field' => 'isoCode', 'value' => 'NONEXISTENT'],
            ],
        ], \JSON_THROW_ON_ERROR));

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(0, $data['total']);
        static::assertEmpty($data['data']);
    }
}
