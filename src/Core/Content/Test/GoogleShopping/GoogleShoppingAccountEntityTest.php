<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\GoogleShopping\DataAbstractionLayer\GoogleAccountCredential;
use Shopware\Core\Content\GoogleShopping\GoogleShoppingAccountEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use function Flag\skipTestNext6050;

class GoogleShoppingAccountEntityTest extends TestCase
{
    use IntegrationTestBehaviour;
    use GoogleShoppingIntegration;

    /** @var EntityRepository */
    private $repository;

    /** @var Context */
    private $context;

    protected function setUp(): void
    {
        skipTestNext6050($this);

        $this->repository = $this->getContainer()->get('google_shopping_account.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testUpdateAccount(): void
    {
        $result = $this->createGoogleShoppingAccount(Uuid::randomHex());
        $id = $result['id'];
        $credential = $result['credential'];

        $salesChannelId = $this->createSalesChannelGoogleShopping();

        $googleAccount = [
            [
                'id' => $id,
                'salesChannelId' => $salesChannelId,
                'email' => 'fooedited@test.co',
                'name' => 'edited',
                'credential' => $credential,
            ],
        ];

        $this->repository->update($googleAccount, $this->context);

        /** @var GoogleShoppingAccountEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->get($id);

        static::assertEquals('fooedited@test.co', $entity->getEmail());
        static::assertEquals('edited', $entity->getName());
        static::assertEquals(new GoogleAccountCredential($credential), $entity->getCredential());
        static::assertEquals($id, $entity->getId());
    }

    public function testCreateAccount(): void
    {
        $result = $this->createGoogleShoppingAccount(Uuid::randomHex());
        $id = $result['id'];
        $credential = $result['credential'];
        /** @var GoogleShoppingAccountEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->get($id);

        static::assertEquals('foo@test.co', $entity->getEmail());
        static::assertEquals('test', $entity->getName());
        static::assertEquals(new GoogleAccountCredential($credential), $entity->getCredential());
        static::assertEquals($id, $entity->getId());
    }

    public function testDeleteAccount(): void
    {
        $result = $this->createGoogleShoppingAccount(Uuid::randomHex());

        $this->repository->delete([['id' => $result['id']]], $this->context);

        /** @var GoogleShoppingAccountEntity $entity */
        $entity = $this->repository->search(new Criteria([$result['id']]), $this->context)->get($result['id']);

        static::assertNull($entity);
    }
}
