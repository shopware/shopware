<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Integration;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Integration\IntegrationCollection;

/**
 * @internal
 */
class IntegrationRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<IntegrationCollection>
     */
    private $repository;

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('integration.repository');
    }

    public function testCreationWithAccessKeys(): void
    {
        $id = Uuid::randomHex();

        $records = [
            [
                'id' => $id,
                'label' => 'My app',
                'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
                'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
            ],
        ];

        $context = Context::createDefaultContext();

        $this->repository->create($records, $context);

        $entities = $this->repository->search(new Criteria([$id]), $context);
        $entity = $entities
            ->getEntities()
            ->first();

        static::assertNotNull($entity);
        static::assertEquals(1, $entities->count());
        static::assertEquals('My app', $entity->getLabel());
    }

    public function testCreationAdminDefaultsToFalse(): void
    {
        $id = Uuid::randomHex();

        $records = [
            [
                'id' => $id,
                'label' => 'My app',
                'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
                'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
            ],
        ];

        $context = Context::createDefaultContext();

        $this->repository->create($records, $context);

        $entities = $this->repository->search(new Criteria([$id]), $context);
        $entity = $entities
            ->getEntities()
            ->first();

        static::assertNotNull($entity);
        static::assertEquals(1, $entities->count());
        static::assertEquals('My app', $entity->getLabel());
        static::assertFalse($entity->getAdmin());
    }

    public function testCreationWithAdminRole(): void
    {
        $id = Uuid::randomHex();

        $records = [
            [
                'id' => $id,
                'label' => 'My app',
                'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
                'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
                'admin' => true,
            ],
        ];

        $context = Context::createDefaultContext();

        $this->repository->create($records, $context);

        $entities = $this->repository->search(new Criteria([$id]), $context);
        $entity = $entities
            ->getEntities()
            ->first();

        static::assertNotNull($entity);
        static::assertEquals(1, $entities->count());
        static::assertEquals('My app', $entity->getLabel());
        static::assertTrue($entity->getAdmin());
    }
}
