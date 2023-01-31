<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Repository;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('customer-order')]
class CustomerTagTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository
     */
    private $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->getContainer()->get('customer.repository');
    }

    public function testEqualsAnyFilter(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $tag1 = Uuid::randomHex();
        $tag2 = Uuid::randomHex();
        $tag3 = Uuid::randomHex();
        $notAssigned = Uuid::randomHex();

        $this->createCustomer($id1, [
            ['id' => $tag1, 'name' => 'tag1'],
            ['id' => $tag3, 'name' => 'tag3'],
        ]);

        $this->createCustomer($id2, [
            ['id' => $tag2, 'name' => 'tag2'],
            ['id' => $tag1, 'name' => 'tag1'],
        ]);

        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('customer.tagIds', [$tag1]));
        $ids = $this->repository->searchIds($criteria, $context);

        static::assertContains($id1, $ids->getIds());
        static::assertContains($id2, $ids->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('customer.tagIds', [$tag2]));
        $ids = $this->repository->searchIds($criteria, $context);

        static::assertNotContains($id1, $ids->getIds());
        static::assertContains($id2, $ids->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('customer.tagIds', [$notAssigned]));
        $ids = $this->repository->searchIds($criteria, $context);

        static::assertNotContains($id1, $ids->getIds());
        static::assertNotContains($id2, $ids->getIds());
    }

    public function testNotEqualsAnyFilter(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $tag1 = Uuid::randomHex();
        $tag2 = Uuid::randomHex();
        $tag3 = Uuid::randomHex();
        $notAssigned = Uuid::randomHex();

        $this->createCustomer($id1, [
            ['id' => $tag1, 'name' => 'tag1'],
            ['id' => $tag3, 'name' => 'tag3'],
        ]);

        $this->createCustomer($id2, [
            ['id' => $tag2, 'name' => 'tag2'],
            ['id' => $tag1, 'name' => 'tag1'],
        ]);

        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(
            new NotFilter(NotFilter::CONNECTION_OR, [
                new EqualsAnyFilter('customer.tagIds', [$notAssigned]),
            ])
        );
        $ids = $this->repository->searchIds($criteria, $context);

        static::assertContains($id1, $ids->getIds());
        static::assertContains($id2, $ids->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(
            new NotFilter(NotFilter::CONNECTION_OR, [
                new EqualsAnyFilter('customer.tagIds', [$tag2]),
            ])
        );
        $ids = $this->repository->searchIds($criteria, $context);

        static::assertContains($id1, $ids->getIds());
        static::assertNotContains($id2, $ids->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(
            new NotFilter(NotFilter::CONNECTION_OR, [
                new EqualsAnyFilter('customer.tagIds', [$notAssigned]),
            ])
        );
        $ids = $this->repository->searchIds($criteria, $context);

        static::assertContains($id1, $ids->getIds());
        static::assertContains($id2, $ids->getIds());
    }

    /**
     * @param array<int, array<string, string>> $tags
     */
    private function createCustomer(string $id, array $tags): void
    {
        $data = [
            'id' => $id,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'firstName' => 'not',
                'lastName' => 'not',
                'city' => 'not',
                'street' => 'not',
                'zipcode' => 'not',
                'salutationId' => $this->getValidSalutationId(),
                'country' => ['name' => 'not'],
            ],
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'not12345',
            'lastName' => 'not',
            'firstName' => 'not',
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => 'not',
            'tags' => $tags,
        ];

        $context = Context::createDefaultContext();

        $this->repository->create([$data], $context);
    }
}
