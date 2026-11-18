<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Controller\AdminTagController;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerTag\CustomerTagDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Tag\Service\FilterTagIdsService;
use Shopware\Core\System\Tag\Struct\FilteredTagIdsStruct;
use Shopware\Core\System\Tag\TagDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AdminTagController::class)]
class AdminTagControllerTest extends TestCase
{
    public function testFilterIds(): void
    {
        $filterTagIdsService = static::createStub(FilterTagIdsService::class);
        $controller = new AdminTagController($filterTagIdsService, $this->createCriteriaValidator());

        $response = $controller->filterIds(new Request(), new Criteria(), Context::createDefaultContext());
        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"total":0,"ids":[]}', $response->getContent());
    }

    public function testFilterIdsRejectsFilterOnUnreadableAssociation(): void
    {
        $filterTagIdsService = $this->createMock(FilterTagIdsService::class);
        $filterTagIdsService->expects($this->never())->method('filterIds');
        $controller = new AdminTagController($filterTagIdsService, $this->createCriteriaValidator());

        $criteria = new Criteria();
        $criteria->addFilter(new PrefixFilter('customers.email', 'b2b'));

        $this->expectExceptionObject(new MissingPrivilegeException(['customer:read']));

        $controller->filterIds(new Request(), $criteria, $this->createContext(['tag:read']));
    }

    public function testFilterIdsAllowsFilterOnReadableAssociation(): void
    {
        $filterTagIdsService = $this->createMock(FilterTagIdsService::class);
        $filterTagIdsService->expects($this->once())
            ->method('filterIds')
            ->willReturn(new FilteredTagIdsStruct(['tag-id'], 1));
        $controller = new AdminTagController($filterTagIdsService, $this->createCriteriaValidator());

        $criteria = new Criteria();
        $criteria->addFilter(new PrefixFilter('customers.email', 'b2b'));

        $response = $controller->filterIds(new Request(), $criteria, $this->createContext(['tag:read', 'customer:read']));
        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"total":1,"ids":["tag-id"]}', $response->getContent());
    }

    private function createCriteriaValidator(): AclCriteriaValidator
    {
        return new AclCriteriaValidator(new StaticDefinitionInstanceRegistry(
            [
                TagDefinition::class,
                CustomerDefinition::class,
                CustomerTagDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        ));
    }

    /**
     * @param list<string> $privileges
     */
    private function createContext(array $privileges): Context
    {
        $source = new AdminApiSource('user-id');
        $source->setIsAdmin(false);
        $source->setPermissions($privileges);

        return new Context($source);
    }
}
