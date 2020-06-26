<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\ProductFeatureSet;

use Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Test\EntityFixturesBase;

trait ProductFeatureSetFixtures
{
    use EntityFixturesBase;

    /**
     * @var array
     */
    public $featureSetFixtures;

    /**
     * @before
     */
    public function initializeFeatureSetFixtures(): void
    {
        $this->featureSetFixtures = [
            'FeatureSetBasic' => [
                'id' => Uuid::randomHex(),
                'name' => 'Basic',
                'description' => 'This is a basic template entity',
                'features' => [],
            ],
            'FeatureSetComplete' => [
                'id' => Uuid::randomHex(),
                'name' => 'Template with relations',
                'description' => 'This template contains features',
                'features' => [
                    [
                        'id' => 'description',
                        'type' => 'product',
                        'position' => 1,
                    ],
                    [
                        'id' => 'listingPrices',
                        'type' => 'product',
                        'position' => 2,
                    ],
                ],
            ],
        ];
    }

    public function getBasicFeatureSet(): ProductFeatureSetEntity
    {
        return $this->getFeatureSetFixture('FeatureSetBasic');
    }

    public function getCompleteFeatureSet(): ProductFeatureSetEntity
    {
        return $this->getFeatureSetFixture('FeatureSetComplete');
    }

    private function getFeatureSetFixture(string $fixtureName): ProductFeatureSetEntity
    {
        /** @var ProductFeatureSetEntity $template */
        $template = $this->createFixture(
            $fixtureName,
            $this->featureSetFixtures,
            self::getFixtureRepository(ProductFeatureSetDefinition::ENTITY_NAME)
        );

        return $template;
    }
}
