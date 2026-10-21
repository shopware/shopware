<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Api\FlowActionCollector;
use Shopware\Core\Content\Flow\Api\FlowActionCollectorResponse;
use Shopware\Core\Content\Flow\Api\FlowActionDefinition;
use Shopware\Core\Content\Flow\FlowCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\Generator\FlowGenerator;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorResponse;
use Shopware\Core\Framework\Event\BusinessEventDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Tag\TagCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(FlowGenerator::class)]
class FlowGeneratorTest extends TestCase
{
    public function testRuleIdsAreCachedWhileNonEmptyAndFetchedAgainOnceExhausted(): void
    {
        $ruleFetches = 0;
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturnCallback(
            static function (string $sql) use (&$ruleFetches): array {
                if (str_contains($sql, 'FROM rule ')) {
                    ++$ruleFetches;

                    return ['rule-1', 'rule-2'];
                }

                return ['mail-1'];
            }
        );
        $connection->method('fetchAllKeyValue')->willReturn(['tag-1' => 'Tag']);

        /** @var \ArrayObject<int, list<string>> $ruleLists */
        $ruleLists = new \ArrayObject();
        $faker = new class($ruleLists) extends Generator {
            /**
             * @param \ArrayObject<int, list<string>> $ruleLists
             */
            public function __construct(private readonly \ArrayObject $ruleLists)
            {
                parent::__construct();
            }

            /**
             * @param string $format
             * @param array<mixed> $arguments
             */
            public function format($format, $arguments = []): mixed
            {
                if ($format === 'boolean') {
                    return true;
                }

                if ($format === 'word') {
                    return 'word';
                }

                $elements = $arguments[0];
                \assert(\is_array($elements));

                if ($elements === [] || \is_string(reset($elements))) {
                    $this->ruleLists->append(array_values($elements));
                }

                return $elements === [] ? null : reset($elements);
            }
        };

        $actions = [];
        for ($i = 0; $i < 200; ++$i) {
            $actions['custom.action.' . $i] = new FlowActionDefinition('custom.action.' . $i, []);
        }

        $eventCollector = static::createStub(BusinessEventCollector::class);
        $eventCollector->method('collect')->willReturn(
            new BusinessEventCollectorResponse([new BusinessEventDefinition('checkout.order.placed', \stdClass::class, [])])
        );
        $actionCollector = static::createStub(FlowActionCollector::class);
        $actionCollector->method('collect')->willReturn(new FlowActionCollectorResponse($actions));

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturnCallback(
            static fn (string $name) => $name === 'tag'
                ? StaticEntityRepository::of(TagCollection::class, [['tag-1']])
                : StaticEntityRepository::of(FlowCollection::class, [])
        );

        $demodataContext = static::createStub(DemodataContext::class);
        $demodataContext->method('getFaker')->willReturn($faker);
        $demodataContext->method('getConsole')->willReturn(static::createStub(SymfonyStyle::class));
        $demodataContext->method('getContext')->willReturn(Context::createDefaultContext());

        (new FlowGenerator($connection, $registry, $eventCollector, $actionCollector))->generate(30, $demodataContext);

        $ruleLists = $ruleLists->getArrayCopy();
        static::assertGreaterThan(2, \count($ruleLists));
        static::assertNotContains([], $ruleLists, 'an exhausted rule list must be fetched again, not served from the cache');
        static::assertLessThan(\count($ruleLists), $ruleFetches, 'a non-empty rule list must be served from the cache');
        static::assertGreaterThan(1, $ruleFetches);
    }
}
