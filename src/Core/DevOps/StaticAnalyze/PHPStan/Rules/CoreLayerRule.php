<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use Doctrine\DBAL\Connection;
use PHPat\Selector\Selector;
use PHPat\Selector\SelectorInterface;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;
use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Architecture boundaries inside the Core bundle and between Storefront and Core.
 *
 * @internal
 */
#[Package('framework')]
class CoreLayerRule
{
    private const CORE = 'Shopware\Core';
    private const FRAMEWORK = 'Shopware\Core\Framework';
    private const SYSTEM = 'Shopware\Core\System';
    private const CONTENT = 'Shopware\Core\Content';
    private const CHECKOUT = 'Shopware\Core\Checkout';
    private const DEV_OPS = 'Shopware\Core\DevOps';

    /**
     * Matches every Shopware class in a `Test` namespace segment, e.g. Shopware\Core\Test\Stub or Shopware\Core\Content\Test\Category.
     */
    private const TEST_NAMESPACE_PATTERN = '/^Shopware\\\\.*\\\\Test\\\\/';

    #[TestRule]
    public function frameworkShouldNotDependOnBusinessDomains(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::FRAMEWORK))
            ->excluding(self::testCode())
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace(self::CONTENT),
                Selector::inNamespace(self::CHECKOUT),
            )
            ->because('Framework is the base layer and must not know about business domains.');
    }

    #[TestRule]
    public function dataAbstractionLayerShouldStayGeneric(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::FRAMEWORK . '\DataAbstractionLayer'))
            ->excluding(self::testCode())
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace(self::SYSTEM),
                Selector::inNamespace(self::CONTENT),
                Selector::inNamespace(self::CHECKOUT),
            )
            ->because('The DAL is generic infrastructure and must not know about concrete entities.');
    }

    #[TestRule]
    public function foundationModulesShouldStayIndependent(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::inNamespace(self::FRAMEWORK . '\Struct'),
                Selector::inNamespace(self::FRAMEWORK . '\Util'),
                Selector::inNamespace(self::FRAMEWORK . '\Uuid'),
            )
            ->excluding(self::testCode())
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace(self::SYSTEM),
                Selector::inNamespace(self::CONTENT),
                Selector::inNamespace(self::CHECKOUT),
                Selector::inNamespace(self::FRAMEWORK . '\DataAbstractionLayer'),
                Selector::inNamespace(self::FRAMEWORK . '\Api'),
                Selector::inNamespace(self::FRAMEWORK . '\App'),
                Selector::inNamespace(self::FRAMEWORK . '\Plugin'),
            )
            ->because('Struct, Util and Uuid are low-level building blocks that every other module may use.');
    }

    #[TestRule]
    public function migrationsShouldNotUseEntityLayer(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::extends(MigrationStep::class))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::classname(EntityRepository::class),
                Selector::extends(EntityDefinition::class),
                Selector::extends(Entity::class),
            )
            ->because('Migrations must keep working when entity definitions change later, so they use plain SQL.');
    }

    #[TestRule]
    public function storefrontControllersShouldNotAccessStorageDirectly(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Shopware\Storefront\Controller'))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::classname(EntityRepository::class),
                Selector::classname(Connection::class),
            )
            ->because('Storefront controllers delegate to page loaders and Store API routes, so headless clients get the same behaviour.');
    }

    #[TestRule]
    public function dataObjectsShouldNotDependOnServices(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::extends(Entity::class),
                Selector::implements(ShopwareEvent::class),
            )
            ->excluding(self::testCode())
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::classname(EntityRepository::class),
                Selector::classname(Connection::class),
                Selector::classname(ContainerInterface::class),
                Selector::implements(ContainerInterface::class),
                Selector::classname(EventDispatcherInterface::class),
                Selector::classname(SymfonyEventDispatcherInterface::class),
                Selector::classname(SystemConfigService::class),
            )
            ->because('Entities and events carry data and must not reach for services.');
    }

    #[TestRule]
    public function productionCodeShouldNotDependOnTestCode(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::CORE))
            ->excluding(
                Selector::inNamespace(self::DEV_OPS),
                self::testCode(),
            )
            ->shouldNot()
            ->dependOn()
            ->classes(self::testCode())
            ->because('Runtime code must not rely on test helpers.');
    }

    private static function testCode(): SelectorInterface
    {
        return Selector::classname(self::TEST_NAMESPACE_PATTERN, true);
    }
}
