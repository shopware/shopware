<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\ContentSystem\Cache;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheEvent;
use Shopware\Core\Framework\ContentSystem\Cache\CacheInvalidationSubscriber;
use Shopware\Core\Framework\ContentSystem\ContentSection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;
use Shopware\Storefront\ContentSystem\FooterContentLayout\FooterContentLayoutDefinition;
use Shopware\Storefront\ContentSystem\HeaderContentLayout\HeaderContentLayoutCollection;
use Shopware\Storefront\ContentSystem\HeaderContentLayout\HeaderContentLayoutDefinition;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Header and footer are Storefront tables, so {@see CacheInvalidationSubscriber} learns their names from a
 * container parameter the Storefront fills. Core declares the parameter empty, which only holds while the
 * Storefront bundle is loaded after the Framework bundle.
 *
 * @internal
 */
#[Package('framework')]
class SectionAssignmentWiringTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[TestDox('the storefront contributes the header and footer assignment tables to cache invalidation')]
    public function testStorefrontContributesSectionAssignments(): void
    {
        static::assertSame(
            [
                HeaderContentLayoutDefinition::ENTITY_NAME => ContentSection::HEADER->value,
                FooterContentLayoutDefinition::ENTITY_NAME => ContentSection::FOOTER->value,
            ],
            static::getContainer()->getParameter('shopware.content_system.section_assignment_entities'),
        );
    }

    #[TestDox('writing a header assignment invalidates the layout route tags')]
    public function testHeaderAssignmentWriteInvalidatesRouteTags(): void
    {
        $context = Context::createDefaultContext();
        $layoutId = $this->createLayout(ContentSection::HEADER->value, $context);

        $keys = $this->collectInvalidatedKeys(function () use ($layoutId, $context): void {
            $this->headerRepository()->create(
                [['id' => Uuid::randomHex(), 'contentLayoutId' => $layoutId]],
                $context
            );
        });

        static::assertContains(ContentSection::MAIN->buildLayoutTag($layoutId), $keys);
        static::assertContains(ContentSection::HEADER->buildLayoutTag($layoutId), $keys);
    }

    /**
     * @return list<string>
     */
    private function collectInvalidatedKeys(callable $write): array
    {
        $keys = [];
        $dispatcher = static::getContainer()->get('event_dispatcher');
        static::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $listener = static function (InvalidateCacheEvent $event) use (&$keys): void {
            $keys = [...$keys, ...array_values($event->getKeys())];
        };

        $dispatcher->addListener(InvalidateCacheEvent::class, $listener);

        try {
            $write();

            $invalidator = static::getContainer()->get(CacheInvalidator::class);
            static::assertInstanceOf(CacheInvalidator::class, $invalidator);
            $invalidator->invalidateExpired();
        } finally {
            $dispatcher->removeListener(InvalidateCacheEvent::class, $listener);
        }

        return $keys;
    }

    private function createLayout(string $rootSource, Context $context): string
    {
        $id = Uuid::randomHex();

        $this->layoutRepository()->create([[
            'id' => $id,
            'name' => 'cache-invalidation-layout',
            'version' => '1.0.0',
            'rootSource' => $rootSource,
            'layout' => [
                ['id' => Uuid::randomHex(), 'component' => TestElementTypeLoader::RESOLVABLE, 'properties' => []],
            ],
        ]], $context);

        return $id;
    }

    /**
     * @return EntityRepository<HeaderContentLayoutCollection>
     */
    private function headerRepository(): EntityRepository
    {
        $repository = static::getContainer()->get(HeaderContentLayoutDefinition::ENTITY_NAME . '.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }

    /**
     * @return EntityRepository<ContentLayoutCollection>
     */
    private function layoutRepository(): EntityRepository
    {
        $repository = static::getContainer()->get('content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
