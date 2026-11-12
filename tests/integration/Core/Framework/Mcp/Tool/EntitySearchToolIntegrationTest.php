<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
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

        $criteriaBuilder = static::getContainer()->get(RequestCriteriaBuilder::class);
        \assert($criteriaBuilder instanceof RequestCriteriaBuilder);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $encoder = static::getContainer()->get(JsonEntityEncoder::class);
        \assert($encoder instanceof JsonEntityEncoder);

        $criteriaValidator = static::getContainer()->get(AclCriteriaValidator::class);
        \assert($criteriaValidator instanceof AclCriteriaValidator);

        $this->tool = new EntitySearchTool($registry, $criteriaBuilder, $contextProvider, $encoder, $criteriaValidator);
    }

    public function testSearchCurrencyReturnsResults(): void
    {
        $output = ($this->tool)('currency');

        static::assertFalse($output->isError());
        static::assertNotCount(0, $output->data);
        static::assertGreaterThan(0, $output->meta['total']);
        static::assertSame(1, $output->meta['page']);
    }

    public function testSearchWithFilter(): void
    {
        $output = ($this->tool)('currency', json_encode([
            'filter' => [
                ['type' => 'equals', 'field' => 'isoCode', 'value' => 'EUR'],
            ],
        ], \JSON_THROW_ON_ERROR));

        static::assertFalse($output->isError());
        static::assertSame(1, $output->meta['total']);
        static::assertSame('EUR', $output->data[0]['isoCode']);
    }

    public function testSearchWithPagination(): void
    {
        $output = ($this->tool)('currency', json_encode([
            'limit' => 1,
            'page' => 1,
        ], \JSON_THROW_ON_ERROR));

        static::assertFalse($output->isError());
        static::assertSame(1, $output->meta['limit']);
        static::assertSame(1, $output->meta['page']);
        static::assertCount(1, $output->data);
    }

    public function testSearchWithSorting(): void
    {
        $output = ($this->tool)('currency', json_encode([
            'sort' => [
                ['field' => 'isoCode', 'order' => 'ASC'],
            ],
        ], \JSON_THROW_ON_ERROR));

        static::assertFalse($output->isError());
        static::assertGreaterThan(0, $output->meta['total']);

        $isoCodes = array_column($output->data, 'isoCode');
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

        static::assertFalse($output->isError());
        static::assertSame(1, $output->meta['total']);
        static::assertArrayHasKey('locale', $output->data[0]);
        static::assertNotNull($output->data[0]['locale']);
    }

    public function testSearchReturnsEmptyForNonExistentFilter(): void
    {
        $output = ($this->tool)('currency', json_encode([
            'filter' => [
                ['type' => 'equals', 'field' => 'isoCode', 'value' => 'NONEXISTENT'],
            ],
        ], \JSON_THROW_ON_ERROR));

        static::assertFalse($output->isError());
        static::assertSame(0, $output->meta['total']);
        static::assertCount(0, $output->data);
    }
}
