<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Administration;
use Shopware\Administration\DependencyInjection\AdministrationMigrationCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('administration')]
#[CoversClass(Administration::class)]
class AdministrationTest extends TestCase
{
    public function testTemplatePriority(): void
    {
        $administration = new Administration();

        static::assertEquals(-1, $administration->getTemplatePriority());
    }

    public function testBundle(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        static::assertNotContains(
            AdministrationMigrationCompilerPass::class,
            $this->toClassNames($container->getCompilerPassConfig()->getPasses())
        );

        $administration = new Administration();
        $administration->build($container);

        static::assertContains(
            AdministrationMigrationCompilerPass::class,
            $this->toClassNames($container->getCompilerPassConfig()->getPasses())
        );
    }

    /**
     * @param CompilerPassInterface[] $initialPasses
     *
     * @return array<int, string>
     */
    protected function toClassNames(array $initialPasses): array
    {
        $result = [];
        foreach ($initialPasses as $pass) {
            $result[] = $pass::class;
        }

        return $result;
    }
}
