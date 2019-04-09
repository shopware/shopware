<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Uuid\Uuid;

class PropertyGroupGenerator implements DemodataGeneratorInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $propertyGroupRepository;

    public function __construct(EntityRepositoryInterface $propertyGroupRepository)
    {
        $this->propertyGroupRepository = $propertyGroupRepository;
    }

    public function getDefinition(): string
    {
        return PropertyGroupDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $context->getConsole()->progressStart($numberOfItems);

        $optionIds = [];

        for ($i = 0; $i <= $numberOfItems; ++$i) {
            $options = [];

            $x = random_int(20, 100);

            for ($i2 = 0; $i2 <= $x; ++$i2) {
                $id = Uuid::randomHex();
                $optionIds[] = $id;
                $options[] = ['id' => $id, 'name' => $context->getFaker()->colorName];
            }

            $this->propertyGroupRepository->create(
                [
                    [
                        'id' => Uuid::randomHex(),
                        'name' => $context->getFaker()->word,
                        'options' => $options,
                        'description' => $context->getFaker()->text,
                        'sorting_type' => 'numeric',
                        'display_type' => 'text',
                    ],
                ],
                $context->getContext()
            );

            $context->getConsole()->progressAdvance(1);
        }

        $context->getConsole()->progressFinish();

        $context->add(PropertyGroupDefinition::class, ...$optionIds);
    }
}
