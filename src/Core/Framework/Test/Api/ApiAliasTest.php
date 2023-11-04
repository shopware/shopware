<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Kernel;

/**
 * @internal
 *
 * @group skip-paratest
 */
class ApiAliasTest extends TestCase
{
    use KernelTestBehaviour;

    public function testUniqueAliases(): void
    {
        $classLoader = KernelLifecycleManager::getClassLoader();
        $classes = array_keys($classLoader->getClassMap());

        if (!isset($classes[Kernel::class])) {
            static::markTestSkipped('This test does not work if the root package is shopware/platform');
        }

        $entities = $this->getContainer()->get(DefinitionInstanceRegistry::class)
            ->getDefinitions();

        $aliases = array_keys($entities);
        $aliases = array_flip($aliases);

        $count = is_countable($aliases) ? \count($aliases) : 0;

        foreach ($classes as $class) {
            $parts = explode('\\', $class);
            if ($parts[0] !== 'Shopware') {
                continue;
            }

            $reflector = new \ReflectionClass($class);

            if (!$reflector->isSubclassOf(Struct::class)) {
                continue;
            }

            if ($reflector->isAbstract() || $reflector->isInterface() || $reflector->isTrait()) {
                continue;
            }

            if ($reflector->isSubclassOf(AggregationResult::class)) {
                continue;
            }

            $instance = $reflector->newInstanceWithoutConstructor();

            if ($instance instanceof Entity) {
                continue;
            }

            if (!$instance instanceof Struct) {
                continue;
            }

            $alias = $instance->getApiAlias();

            if ($alias === 'aggregation-' || $alias === 'dal_entity_search_result') {
                continue;
            }

            static::assertArrayNotHasKey($alias, $aliases);
            $aliases[$alias] = true;
        }

        static::assertTrue((is_countable($aliases) ? \count($aliases) : 0) > $count, 'Validated only entities, please check registered classes of class loader');
    }
}
