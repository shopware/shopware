<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook\Hookable;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\ArrayBusinessEvent;
use Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\CollectionBusinessEvent;
use Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\EntityBusinessEvent;
use Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\NestedEntityBusinessEvent;
use Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\ScalarBusinessEvent;
use Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\StructuredArrayObjectBusinessEvent;
use Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\StructuredObjectBusinessEvent;
use Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\UnstructuredObjectBusinessEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\BusinessEventEncoder;
use Shopware\Core\Framework\Webhook\Hookable\HookableBusinessEvent;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\System\Tax\TaxEntity;

class HookableBusinessEventTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testGetter(): void
    {
        $scalarEvent = new ScalarBusinessEvent();
        $event = HookableBusinessEvent::fromBusinessEvent(
            $scalarEvent,
            $this->getContainer()->get(BusinessEventEncoder::class)
        );

        static::assertEquals($scalarEvent->getName(), $event->getName());
        $shopwareVersion = $this->getContainer()->getParameter('kernel.shopware_version');
        static::assertEquals($scalarEvent->getEncodeValues($shopwareVersion), $event->getWebhookPayload());
    }

    /**
     * @dataProvider getEventsWithoutPermissions
     */
    public function testIsAllowedForNonEntityBasedEvents(BusinessEventInterface $rootEvent): void
    {
        $event = HookableBusinessEvent::fromBusinessEvent(
            $rootEvent,
            $this->getContainer()->get(BusinessEventEncoder::class)
        );

        static::assertTrue($event->isAllowed(Uuid::randomHex(), new AclPrivilegeCollection([])));
    }

    /**
     * @dataProvider getEventsWithPermissions
     */
    public function testIsAllowedForEntityBasedEvents(BusinessEventInterface $rootEvent): void
    {
        $event = HookableBusinessEvent::fromBusinessEvent(
            $rootEvent,
            $this->getContainer()->get(BusinessEventEncoder::class)
        );

        $allowedPermissions = new AclPrivilegeCollection([
            TaxDefinition::ENTITY_NAME . ':' . AclRoleDefinition::PRIVILEGE_READ,
        ]);
        static::assertTrue($event->isAllowed(Uuid::randomHex(), $allowedPermissions));

        $notAllowedPermissions = new AclPrivilegeCollection([
            ProductDefinition::ENTITY_NAME . ':' . AclRoleDefinition::PRIVILEGE_READ,
        ]);
        static::assertFalse($event->isAllowed(Uuid::randomHex(), $notAllowedPermissions));
    }

    public function getEventsWithoutPermissions(): array
    {
        return [
            [new ScalarBusinessEvent()],
            [new StructuredObjectBusinessEvent()],
            [new StructuredArrayObjectBusinessEvent()],
            [new UnstructuredObjectBusinessEvent()],
        ];
    }

    public function getEventsWithPermissions(): array
    {
        return [
            [new EntityBusinessEvent($this->getTaxEntity())],
            [new CollectionBusinessEvent($this->getTaxCollection())],
            [new ArrayBusinessEvent($this->getTaxCollection())],
            [new NestedEntityBusinessEvent($this->getTaxEntity())],
        ];
    }

    private function getTaxEntity(): TaxEntity
    {
        /** @var EntityRepositoryInterface $taxRepo */
        $taxRepo = $this->getContainer()->get('tax.repository');

        return $taxRepo->search(new Criteria(), Context::createDefaultContext())->first();
    }

    private function getTaxCollection(): TaxCollection
    {
        /** @var EntityRepositoryInterface $taxRepo */
        $taxRepo = $this->getContainer()->get('tax.repository');

        return $taxRepo->search(new Criteria(), Context::createDefaultContext())->getEntities();
    }
}
