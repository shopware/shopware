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
        $ruleQueryResults = [0 => ['mail-1'], 1 => ['rule-1', 'rule-2']];
        $connection->method('fetchFirstColumn')->willReturnCallback(
            static function (string $sql) use (&$ruleFetches, $ruleQueryResults): array {
                $isRuleQuery = (int) str_contains($sql, 'FROM rule ');
                $ruleFetches += $isRuleQuery;

                return $ruleQueryResults[$isRuleQuery];
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
             * @param int $chanceOfGettingTrue
             */
            public function boolean($chanceOfGettingTrue = 50): bool
            {
                return true;
            }

            public function word(): string
            {
                return 'word';
            }

            /**
             * @param array<mixed> $array
             */
            public function randomElement($array = ['a', 'b', 'c']): mixed
            {
                $this->recordStringList($array);

                return reset($array);
            }

            /**
             * @param array<mixed> $array
             * @param int $count
             */
            public function randomElements($array = ['a', 'b', 'c'], $count = 1): mixed
            {
                $this->recordStringList($array);

                return reset($array);
            }

            /**
             * @param array<mixed> $array
             */
            private function recordStringList(array $array): void
            {
                $stringLists = array_filter(
                    [array_values($array)],
                    static fn (array $list): bool => array_filter($list, 'is_string') === $list
                );
                $this->ruleLists->exchangeArray(array_merge($this->ruleLists->getArrayCopy(), $stringLists));
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
        $registry->method('getRepository')->willReturnMap([
            ['tag', StaticEntityRepository::of(TagCollection::class, [['tag-1']])],
            ['flow', StaticEntityRepository::of(FlowCollection::class, [])],
        ]);

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
