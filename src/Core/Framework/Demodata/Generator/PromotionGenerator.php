<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Generator;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('core')]
class PromotionGenerator implements DemodataGeneratorInterface
{
    private SymfonyStyle $io;

    private Generator $faker;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly DefinitionInstanceRegistry $registry
    ) {
    }

    public function getDefinition(): string
    {
        return PromotionDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $this->faker = $context->getFaker();
        $this->io = $context->getConsole();

        $this->createPromotions($context->getContext(), $numberOfItems);
    }

    private function createPromotions(Context $context, int $count): void
    {
        $salesChannels = $this->getSalesChannels();

        $this->io->progressStart($count);

        $payload = [];
        for ($i = 0; $i < $count; ++$i) {
            $promotion = $this->createPromotion($salesChannels);

            $payload[] = $promotion;

            if (\count($payload) >= 20) {
                $this->io->progressAdvance(\count($payload));
                $this->write($payload, $context);
                $payload = [];
            }
        }

        if (!empty($payload)) {
            $this->write($payload, $context);
        }

        $this->io->progressFinish();
    }

    /**
     * @param list<array<string, mixed>> $payload
     */
    private function write(array $payload, Context $context): void
    {
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $this->registry->getRepository('promotion')->create($payload, $context);

        $context->removeState(EntityIndexerRegistry::DISABLE_INDEXING);
    }

    /**
     * @param list<array<string, string|int>> $salesChannels
     *
     * @return array<string, mixed>
     */
    private function createPromotion(array $salesChannels): array
    {
        return [
            'id' => Uuid::randomHex(),
            'name' => $this->faker->format('productName'),
            'active' => true,
            'useSetGroups' => false,
            'salesChannels' => $salesChannels,
            'code' => $this->faker->unique()->format('promotionCode'),
            'useCodes' => true,
            'discounts' => $this->createDiscounts(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function createDiscounts(): array
    {
        $discounts = [];
        $count = $this->faker->randomDigit() / 3;

        for ($i = 0; $i <= $count; ++$i) {
            $scope = $this->faker->randomElement([PromotionDiscountEntity::SCOPE_CART, PromotionDiscountEntity::SCOPE_DELIVERY]);
            $type = $this->faker->randomElement([PromotionDiscountEntity::TYPE_ABSOLUTE, PromotionDiscountEntity::TYPE_PERCENTAGE]);
            $value = $this->faker->randomFloat(2, 0.01, 100);
            if ($type === PromotionDiscountEntity::TYPE_PERCENTAGE || $scope === PromotionDiscountEntity::SCOPE_DELIVERY) {
                $value /= 10;
            }

            $discounts[] = [
                'scope' => $scope,
                'type' => $type,
                'value' => $value,
                'considerAdvancedRules' => false,
            ];
        }

        return $discounts;
    }

    /**
     * @return list<array<string, string|int>>
     */
    private function getSalesChannels(): array
    {
        $ids = $this->connection->fetchAllAssociative('SELECT LOWER(HEX(id)) as id FROM `sales_channel` LIMIT 100');

        return array_map(fn ($id) => ['salesChannelId' => $id['id'], 'priority' => 1], $ids);
    }
}
