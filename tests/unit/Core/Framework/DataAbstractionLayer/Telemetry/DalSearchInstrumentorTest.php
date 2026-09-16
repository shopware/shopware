<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Telemetry\DalSearchInstrumentor;
use Shopware\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfigProvider;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DalSearchInstrumentor::class)]
class DalSearchInstrumentorTest extends TestCase
{
    /**
     * @var list<ConfiguredMetric>
     */
    private array $emitted = [];

    public function testMeasureReturnsCallbackResultUnchanged(): void
    {
        $result = new AggregationResultCollection();

        $returned = $this->createInstrumentor()->measure(
            DalSearchInstrumentor::OPERATION_AGGREGATE,
            $this->definition('product'),
            new Criteria(),
            fn (): AggregationResultCollection => $result,
        );

        static::assertSame($result, $returned);
    }

    public function testEmitsDurationWithResolvedLabels(): void
    {
        $this->createInstrumentor()->measure(
            DalSearchInstrumentor::OPERATION_SEARCH_IDS,
            $this->definition('product_price'),
            new Criteria(),
            fn (): IdSearchResult => new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext()),
        );

        static::assertCount(1, $this->emitted);
        $metric = $this->emitted[0];
        static::assertSame('dal.search.duration', $metric->name);
        static::assertIsFloat($metric->value);
        static::assertGreaterThanOrEqual(0.0, $metric->value);
        // product_price buckets to the product group via EntityGroupResolver
        static::assertSame('product', $metric->labels['entity_group']);
        static::assertSame('searchIds', $metric->labels['operation']);
    }

    #[DataProvider('esAwareProvider')]
    public function testEsAwareLabelReflectsCriteriaState(bool $addState, string $expected): void
    {
        $criteria = new Criteria();
        if ($addState) {
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        }

        $this->createInstrumentor()->measure(
            DalSearchInstrumentor::OPERATION_SEARCH,
            $this->definition('product'),
            $criteria,
            fn (): IdSearchResult => new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext()),
        );

        static::assertSame($expected, $this->emitted[0]->labels['es_aware']);
    }

    public static function esAwareProvider(): \Generator
    {
        yield 'elasticsearch-aware criteria' => [true, 'yes'];
        yield 'plain criteria' => [false, 'no'];
    }

    public function testBackendIsSqlWhenResultCarriesNoElasticsearchState(): void
    {
        $this->createInstrumentor()->measure(
            DalSearchInstrumentor::OPERATION_SEARCH_IDS,
            $this->definition('product'),
            new Criteria(),
            fn (): IdSearchResult => new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext()),
        );

        static::assertSame('sql', $this->emitted[0]->labels['backend']);
    }

    public function testBackendIsElasticsearchWhenResultCarriesElasticsearchState(): void
    {
        $result = new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext());
        // exactly the state the ES bundle stamps on a result it served (drift guard against the literal)
        $result->addState(ElasticsearchEntitySearcher::RESULT_STATE);

        $this->createInstrumentor()->measure(
            DalSearchInstrumentor::OPERATION_SEARCH_IDS,
            $this->definition('product'),
            new Criteria(),
            fn (): IdSearchResult => $result,
        );

        static::assertSame('elasticsearch', $this->emitted[0]->labels['backend']);
    }

    public function testDoesNotEmitButStillRunsCallbackWhenTelemetryGloballyDisabled(): void
    {
        $result = new AggregationResultCollection();

        $returned = $this->createInstrumentor(globalEnabled: false)->measure(
            DalSearchInstrumentor::OPERATION_AGGREGATE,
            $this->definition('product'),
            new Criteria(),
            fn (): AggregationResultCollection => $result,
        );

        static::assertSame($result, $returned);
        static::assertSame([], $this->emitted);
    }

    public function testDoesNotEmitWhenThisMetricDefinitionIsDisabled(): void
    {
        $this->createInstrumentor(metricEnabled: false)->measure(
            DalSearchInstrumentor::OPERATION_SEARCH,
            $this->definition('product'),
            new Criteria(),
            fn (): IdSearchResult => new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext()),
        );

        static::assertSame([], $this->emitted);
    }

    public function testDoesNotEmitWhenMetricConfigurationIsMissing(): void
    {
        $meter = static::createStub(Meter::class);
        $meter->method('emit')->willReturnCallback(function (ConfiguredMetric $metric): void {
            $this->emitted[] = $metric;
        });
        // a provider without the metric configuration must disable the metric, never break the DAL
        $instrumentor = new DalSearchInstrumentor($meter, new EntityGroupResolver(), new MetricConfigProvider([]), true);

        $instrumentor->measure(
            DalSearchInstrumentor::OPERATION_SEARCH,
            $this->definition('product'),
            new Criteria(),
            fn (): IdSearchResult => new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext()),
        );

        static::assertSame([], $this->emitted);
    }

    private function createInstrumentor(bool $globalEnabled = true, bool $metricEnabled = true): DalSearchInstrumentor
    {
        $meter = static::createStub(Meter::class);
        $meter->method('emit')->willReturnCallback(function (ConfiguredMetric $metric): void {
            $this->emitted[] = $metric;
        });

        $configProvider = new MetricConfigProvider([
            'dal.search.duration' => ['type' => 'histogram', 'description' => 'test', 'enabled' => $metricEnabled],
        ]);

        return new DalSearchInstrumentor($meter, new EntityGroupResolver(), $configProvider, $globalEnabled);
    }

    private function definition(string $entityName): EntityDefinition
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);

        return $definition;
    }
}
