<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class PromotionControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $promotionRepository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $resourceUri;

    /**
     * @var KernelBrowser
     */
    private $api;

    protected function setUp(): void
    {
        $this->promotionRepository = $this->getContainer()->get('promotion.repository');
        $this->context = Context::createDefaultContext();

        $this->api = $this->getBrowser();
        $this->resourceUri = '/api/promotion';
    }

    /**
     * This test verifies that we can successfully
     * create a new promotion with the minimum-required
     * data with our API.
     *
     * @test
     * @group promotions
     *
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    public function testCreatePromotion(): void
    {
        $promotionId = Uuid::randomHex();

        $this->api->request(
            'POST',
            $this->resourceUri,
            [
                'id' => $promotionId,
                'name' => 'Super Sale',
            ]
        );

        $response = $this->api->getResponse();

        // verify HTTP STATUS CODE
        static::assertSame(204, $response->getStatusCode(), $response->getContent());

        /** @var PromotionEntity $promotion */
        $promotion = $this->getPromotionFromDB($promotionId);

        // assert basic values
        static::assertEquals($promotionId, $promotion->getId());
        static::assertEquals('Super Sale', $promotion->getName());
    }

    /**
     * This test verifies that we can read the details of our
     * promotion using the API
     *
     * @test
     * @group promotions
     */
    public function testReadPromotion(): void
    {
        $promotionId = Uuid::randomHex();
        $discountId = Uuid::randomHex();
        $this->insertPromotionInDB($promotionId, $discountId);

        $this->api->request(
            'GET',
            $this->resourceUri . '/' . $promotionId
        );

        $response = $this->api->getResponse();

        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        $json = json_decode($response->getContent(), true);

        static::assertEquals($promotionId, $json['data']['id']);
        static::assertEquals('promotion', $json['data']['type']);
        static::assertEquals('Super Sale', $json['data']['attributes']['name']);
        static::assertTrue($json['data']['attributes']['active']);
    }

    /**
     * This test verifies that we can read the list data of our
     * promotions using the API
     *
     * @test
     * @group promotions
     */
    public function testReadPromotionList(): void
    {
        $promotionId = Uuid::randomHex();
        $discountId = Uuid::randomHex();
        $this->insertPromotionInDB($promotionId, $discountId);

        $this->api->request(
            'GET',
            $this->resourceUri
        );

        $response = $this->api->getResponse();

        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        /** @var array $json */
        $json = json_decode($response->getContent(), true);

        // verify that we have 1 total found promotion
        static::assertEquals(1, $json['meta']['total']);

        // assert values of first promotion
        static::assertEquals($promotionId, $json['data'][0]['id']);
        static::assertEquals('Super Sale', $json['data'][0]['attributes']['name']);
    }

    /**
     * This test verifies that we can update our promotion
     * with the API. In this test we update the name
     * and verify if the new values is stored in the database.
     *
     * @test
     * @group promotions
     */
    public function testPatchPromotion(): void
    {
        $promotionId = Uuid::randomHex();
        $discountId = Uuid::randomHex();
        $this->insertPromotionInDB($promotionId, $discountId);

        $this->api->request(
            'PATCH',
            $this->resourceUri . '/' . $promotionId,
            [
                'name' => 'Super Better Sale',
            ]
        );

        $response = $this->api->getResponse();

        static::assertEquals(204, $response->getStatusCode(), $response->getContent());

        /** @var PromotionEntity $promotion */
        $promotion = $this->getPromotionFromDB($promotionId);

        static::assertEquals('Super Better Sale', $promotion->getName());
    }

    /**
     * This test verifies that we can delete our discount
     * with the API. So we delete a discount from a promotion
     * that only has 1 discount. then we load it from the database and
     * check if no more discounts exist.
     *
     * @test
     * @group promotions
     */
    public function testDeletePromotionDiscount(): void
    {
        $promotionId = Uuid::randomHex();
        $discountId = Uuid::randomHex();
        $this->insertPromotionInDB($promotionId, $discountId);

        $this->api->request(
            'DELETE',
            $this->resourceUri . '/' . $promotionId . '/discounts/' . $discountId
        );

        $response = $this->api->getResponse();

        static::assertEquals(204, $response->getStatusCode(), $response->getContent());

        /** @var PromotionEntity $promotion */
        $promotion = $this->getPromotionFromDB($promotionId);

        static::assertCount(0, $promotion->getDiscounts());
    }

    /**
     * This test verifies that we can update our discount with
     * new values. We change the type and value and then load it from
     * the database and see if it has been correctly updated.
     *
     * @test
     * @group promotions
     *
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    public function testPatchDiscount(): void
    {
        $promotionId = Uuid::randomHex();
        $discountId = Uuid::randomHex();
        $this->insertPromotionInDB($promotionId, $discountId);

        $this->api->request(
            'PATCH',
            $this->resourceUri . '/' . $promotionId . '/discounts/' . $discountId,
            [
                'type' => 'percentage',
                'value' => 12.5,
            ]
        );

        /** @var PromotionEntity $promotion */
        $promotion = $this->getPromotionFromDB($promotionId);

        /** @var PromotionDiscountEntity $discount */
        $discount = $promotion->getDiscounts()->get($discountId);

        static::assertEquals('percentage', $discount->getType());
        static::assertEquals(12.5, $discount->getValue());
    }

    /**
     * This test verifies that we can sucessfully delete a promotion
     * with the API. We add 1 promotion in the database, then delete it
     * using our client, and finally verify if no more promotions exist
     * in the database for this ID.
     *
     * @test
     * @group promotions
     */
    public function testDeletePromotion(): void
    {
        $promotionId = Uuid::randomHex();
        $discountId = Uuid::randomHex();
        $this->insertPromotionInDB($promotionId, $discountId);

        $this->api->request(
            'DELETE',
            '/api/promotion/' . $promotionId
        );

        $response = $this->api->getResponse();

        static::assertEquals(204, $response->getStatusCode(), $response->getContent());

        $promotions = $this->getPromotionFromDB($promotionId);

        static::assertNull($promotions);
    }

    private function getPromotionFromDB(string $id): ?PromotionEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('discounts');

        /** @var PromotionEntity $promotion */
        $promotion = $this->promotionRepository->search($criteria, $this->context)->get($id);

        return $promotion;
    }

    private function insertPromotionInDB(string $id, string $discountId): void
    {
        $this->promotionRepository->create(
            [
                [
                    'id' => $id,
                    'name' => 'Super Sale',
                    'active' => true,
                    'validFrom' => '2019-01-01 00:00:00',
                    'validUntil' => '2030-01-01 00:00:00',
                    'maxRedemptionsGlobal' => 1000,
                    'maxRedemptionsPerCustomer' => 1,
                    'exclusive' => false,
                    'useCodes' => true,
                    'use_setgroups' => false,
                    'code' => 'super19',
                    'customer_restriction' => true,
                    'discounts' => [
                        [
                            'id' => $discountId,
                            'scope' => PromotionDiscountEntity::SCOPE_CART,
                            'type' => PromotionDiscountEntity::TYPE_ABSOLUTE,
                            'value' => 100,
                            'considerAdvancedRules' => false,
                            'graduated' => false,
                        ],
                    ],
                ],
            ],
            $this->context
        );
    }
}
