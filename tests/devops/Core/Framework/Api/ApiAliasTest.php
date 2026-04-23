<?php declare(strict_types=1);

namespace Shopware\Tests\Devops\Core\Framework\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Kernel;

/**
 * @internal
 */
class ApiAliasTest extends TestCase
{
    use KernelTestBehaviour;

    // TODO: fix these duplicate aliases — known bugs to be treated
    private const KNOWN_DUPLICATE_ALIASES = [
        'customer_address_collection',
        'dal_field_sorting',
        'calculated_price',
    ];

    public function testUniqueAliases(): void
    {
        $classMap = KernelLifecycleManager::getClassLoader()->getClassMap();

        if (!isset($classMap[Kernel::class])) {
            static::markTestSkipped('This test does not work if the root package is shopware/platform');
        }

        $entities = self::getContainer()->get(DefinitionInstanceRegistry::class)
            ->getDefinitions();

        $aliases = array_keys($entities);
        $aliases = array_flip($aliases);

        $count = \count($aliases);

        foreach (array_keys($classMap) as $class) {
            $parts = explode('\\', $class);
            if ($parts[0] !== 'Shopware') {
                continue;
            }

            if (!is_subclass_of($class, Struct::class)) {
                continue;
            }

            if (is_subclass_of($class, Aggregation::class) || is_subclass_of($class, AggregationResult::class)) {
                continue;
            }

            if (is_subclass_of($class, Entity::class)) {
                continue;
            }

            $reflector = new \ReflectionClass($class);

            if ($reflector->isAbstract()) {
                continue;
            }

            $instance = $reflector->newInstanceWithoutConstructor();

            $alias = $instance->getApiAlias();

            if ($alias === 'aggregation-' || $alias === 'dal_entity_search_result') {
                continue;
            }

            if (\in_array($alias, self::KNOWN_DUPLICATE_ALIASES, true)) {
                continue;
            }

            static::assertArrayNotHasKey($alias, $aliases);
            $aliases[$alias] = true;
        }

        static::assertTrue(\count($aliases) > $count, 'Validated only entities, please check registered classes of class loader');
    }
}
