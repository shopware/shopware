<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Controller\AdminSearchController;
use Shopware\Administration\Service\AdminSearcher;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

/**
 * @internal
 */
#[Package('administration')]
#[CoversClass(AdminSearchController::class)]
class AdminSearchControllerTest extends TestCase
{
    private AdminSearchController $controller;

    private MockObject&AclCriteriaValidator $criteriaValidator;

    private MockObject&DefinitionInstanceRegistry $definitionInstanceRegistry;

    private MockObject&DefinitionInstanceRegistry $definitionRegistry;

    private MockObject&JsonEntityEncoder $entityEncoder;

    private MockObject&RequestCriteriaBuilder $requestCriteriaBuilder;

    private MockObject&AdminSearcher $searcher;

    private MockObject&DecoderInterface $serializer;

    protected function setUp(): void
    {
        $this->requestCriteriaBuilder = $this->createMock(RequestCriteriaBuilder::class);
        $this->definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $this->searcher = $this->createMock(AdminSearcher::class);
        $this->serializer = $this->createMock(DecoderInterface::class);
        $this->criteriaValidator = $this->createMock(AclCriteriaValidator::class);
        $this->definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $this->entityEncoder = $this->createMock(JsonEntityEncoder::class);

        $this->controller = new AdminSearchController(
            $this->requestCriteriaBuilder,
            $this->definitionInstanceRegistry,
            $this->searcher,
            $this->serializer,
            $this->criteriaValidator,
            $this->definitionRegistry,
            $this->entityEncoder
        );
    }

    public function testSearchWithNoQueryReturnsEmptyData(): void
    {
        $this->serializer->expects(static::once())->method('decode')->willReturn([]);

        $response = $this->controller->search(new Request(), Context::createDefaultContext());

        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"data":[]}', $response->getContent());
    }

    public function testSearchWitMissingPrivilegeReturnsViolations(): void
    {
        $this->serializer->expects(static::once())->method('decode')
            ->willReturn(
                [ProductDefinition::class => ['product'], LandingPageDefinition::class => ['page']]
            );

        $this->definitionInstanceRegistry->expects(static::any())->method('has')
            ->willReturnOnConsecutiveCalls(true, false);

        $validationError = [ProductDefinition::class . ':' . AclRoleDefinition::PRIVILEGE_READ];
        $this->criteriaValidator->expects(static::once())->method('validate')
            ->willReturn($validationError);

        $response = $this->controller->search(new Request(['product' => true, 'page' => true]), Context::createDefaultContext());

        static::assertNotFalse($response->getContent());
        $result = \json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($result);
        static::assertArrayHasKey('data', $result);
        static::assertEquals(
            [
                ProductDefinition::class => [
                    'status' => '403',
                    'code' => 'FRAMEWORK__MISSING_PRIVILEGE_ERROR',
                    'title' => 'Forbidden',
                    'detail' => json_encode(['message' => 'Missing privilege', 'missingPrivileges' => $validationError]),
                    'meta' => ['parameters' => []],
                ]],
            $result['data']
        );
    }

    public function testSearchWitMatchingEntitiesReturnsData(): void
    {
        $this->serializer->expects(static::once())->method('decode')
            ->willReturn(
                [ProductEntity::class => ['product'], LandingPageDefinition::class => ['page']]
            );

        $this->definitionInstanceRegistry->expects(static::any())->method('has')
            ->willReturnOnConsecutiveCalls(true, true);

        $productEntity = new ProductEntity();
        $productEntity->setUniqueIdentifier(Uuid::randomHex());

        $collection = new EntityCollection([$productEntity]);

        $this->searcher->expects(static::once())->method('search')
            ->willReturn([
                ProductEntity::class => [
                    'data' => $collection,
                    'total' => \count($collection),
                ],
                CategoryEntity::class => [
                    'data' => $collection,
                    'total' => \count($collection),
                ],
            ]);

        $response = $this->controller->search(new Request(['product' => true, 'page' => true]), Context::createDefaultContext());

        static::assertNotFalse($response->getContent());
        $result = \json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($result);
        static::assertArrayHasKey('data', $result);
        static::assertArrayHasKey(ProductEntity::class, $result['data']);
        static::assertArrayHasKey(CategoryEntity::class, $result['data']);
    }
}
