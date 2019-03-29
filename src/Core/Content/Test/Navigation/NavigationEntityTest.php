<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Navigation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Navigation\NavigationCollection;
use Shopware\Core\Content\Navigation\NavigationEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class NavigationEntityTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AdminApiTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->getContainer()->get('navigation.repository');
    }

    public function testCreateNavigation(): void
    {
        $id = Uuid::randomHex();

        $data = ['id' => $id, 'name' => 'Main'];

        $context = Context::createDefaultContext();

        $this->repository->create([$data], $context);

        $result = $this->repository->search(new Criteria([$id]), $context);

        static::assertTrue($result->has($id));

        $navigation = $result->get($id);
        static::assertInstanceOf(NavigationEntity::class, $navigation);

        /** @var NavigationEntity $navigation */
        static::assertSame('Main', $navigation->getName());
    }

    public function testCreateWithChildren(): void
    {
        $parentId = Uuid::randomHex();
        $childId1 = Uuid::randomHex();
        $childId2 = Uuid::randomHex();

        $data = [
            'id' => $parentId,
            'name' => 'Main',
            'children' => [
                ['id' => $childId1, 'name' => 'Child1'],
                ['id' => $childId2, 'name' => 'Child2'],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->repository->create([$data], $context);

        $criteria = new Criteria([$parentId]);
        $criteria->addAssociation('navigation.children');

        $result = $this->repository->search($criteria, $context);

        static::assertTrue($result->has($parentId));

        $navigation = $result->get($parentId);
        /** @var NavigationEntity $navigation */
        static::assertInstanceOf(NavigationEntity::class, $navigation);

        static::assertInstanceOf(NavigationCollection::class, $navigation->getChildren());
        static::assertCount(2, $navigation->getChildren());
        static::assertSame(1, $navigation->getLevel());
        static::assertNull($navigation->getPath());

        /** @var NavigationEntity $child */
        foreach ($navigation->getChildren() as $child) {
            static::assertSame('|' . $parentId . '|', $child->getPath());
            static::assertSame(2, $child->getLevel());
            static::assertSame($parentId, $child->getParentId());
        }
    }

    public function testCreateNavigationViaApi(): void
    {
        $parentId = Uuid::randomHex();
        $childId1 = Uuid::randomHex();
        $childId2 = Uuid::randomHex();

        $data = [
            'id' => $parentId,
            'name' => 'Main',
            'children' => [
                ['id' => $childId1, 'name' => 'Child1'],
                ['id' => $childId2, 'name' => 'Child2'],
            ],
        ];

        $this->getClient()->request('POST', '/api/v1/navigation', $data);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode());

        $this->getClient()->request('GET', '/api/v1/navigation/' . $parentId);

        $response = json_decode($this->getClient()->getResponse()->getContent(), true);

        static::assertIsArray($response);
        static::assertArrayHasKey('data', $response);
        static::assertArrayHasKey('attributes', $response['data']);

        $values = $response['data']['attributes'];
        static::assertSame('Main', $values['name']);
    }
}
