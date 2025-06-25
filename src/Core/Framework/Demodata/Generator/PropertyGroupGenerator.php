<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
class PropertyGroupGenerator implements DemodataGeneratorInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $propertyGroupRepository)
    {
    }

    public function getDefinition(): string
    {
        return PropertyGroupDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $exists = $this->propertyGroupRepository->searchIds($criteria, Context::createDefaultContext());

        if ($numberOfItems <= 0 && $exists->getTotal() > 0) {
            return;
        }

        $context->getConsole()->progressStart($numberOfItems);
        $baseData = $this->getBasePropertyGroupData();

        $itemsCreated = 0;
        $groupIterations = array_fill_keys(array_keys($baseData), 0);
        $baseGroupKeys = array_keys($baseData);

        while ($itemsCreated < $numberOfItems) {
            foreach ($baseGroupKeys as $group) {
                if ($itemsCreated >= $numberOfItems) {
                    break;
                }

                $iteration = $groupIterations[$group];
                $groupName = $iteration === 0 ? $group : sprintf('%s_%d', $group, $iteration);

                $propertyOptions = $baseData[$group];
                $mapped = array_map(fn ($option) => ['id' => Uuid::randomHex(), 'name' => $option], $propertyOptions);

                $this->propertyGroupRepository->create(
                    [
                        [
                            'id' => Uuid::randomHex(),
                            'name' => $groupName,
                            'options' => $mapped,
                            'sorting_type' => PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC,
                            'display_type' => PropertyGroupDefinition::DISPLAY_TYPE_TEXT,
                        ],
                    ],
                    $context->getContext()
                );

                ++$itemsCreated;
                ++$groupIterations[$group];
                $context->getConsole()->progressAdvance(1);
            }
        }

        $context->getConsole()->progressFinish();
    }

    /**
     * Returns the base property group data
     *
     * @return array<string, array<string>>
     */
    private function getBasePropertyGroupData(): array
    {
        $colorOptions = ['aliceblue', 'antiquewhite', 'aquamarine', 'azure', 'bakerschocolate', 'beige', 'bisque', 'blanchedalmond', 'blueviolet', 'brass', 'brightgold', 'bronze', 'brown', 'burlywood', 'cadetblue', 'chartreuse', 'chocolate', 'coolcopper', 'copper', 'coral', 'cornflowerblue', 'cornsilk', 'crimson', 'cyan', 'darkblue', 'darkbrown', 'darkcyan', 'darkgoldenrod', 'darkgray', 'darkgreen', 'darkgreencopper', 'darkkhaki', 'darkmagenta', 'darkolivegreen', 'darkorange', 'darkorchid', 'darkpurple', 'darkred', 'darksalmon', 'darkseagreen', 'darkslateblue', 'darkslategray', 'darktan', 'darkturquoise', 'darkviolet', 'darkwood', 'deeppink', 'deepskyblue', 'dimgray', 'dodgerblue', 'dustyrose', 'fadedbrown', 'feldspar', 'firebrick', 'floralwhite', 'forestgreen', 'gainsboro', 'ghostwhite', 'gold', 'goldenrod', 'greencopper', 'greenyellow', 'honeydew', 'hotpink', 'huntergreen', 'indianred', 'indigo', 'ivory', 'khaki', 'lavender', 'lavenderblush', 'lawngreen', 'lemonchiffon', 'lightblue', 'lightcoral', 'lightcyan', 'lightgoldenrodyellow', 'lightgreen', 'lightgrey', 'lightpink', 'lightsalmon', 'lightseagreen', 'lightskyblue', 'lightslateblue', 'lightslategray', 'lightsteelblue', 'lightwood', 'lightyellow', 'limegreen', 'linen', 'mandarinorange', 'mediumaquamarine', 'mediumblue', 'mediumgoldenrod', 'mediumorchid', 'mediumpurple', 'mediumseagreen', 'mediumslateblue', 'mediumspringgreen', 'mediumturquoise', 'mediumvioletred', 'mediumwood', 'midnightblue', 'mintcream', 'mistyrose', 'moccasin', 'navajowhite', 'navyblue', 'neonblue', 'neonpink', 'newmidnightblue', 'newtan', 'oldgold', 'oldlace', 'olivedrab', 'orange', 'orangered', 'orchid', 'palegoldenrod', 'palegreen', 'paleturquoise', 'palevioletred', 'papayawhip', 'peachpuff', 'peru', 'pink', 'plum', 'powderblue', 'quartz', 'richblue', 'rosybrown', 'royalblue', 'saddlebrown', 'salmon', 'sandybrown', 'scarlet', 'seagreen', 'seashell', 'semisweetchocolate', 'sienna', 'skyblue', 'slateblue', 'slategray', 'snow', 'spicypink', 'springgreen', 'steelblue', 'summersky', 'tan', 'thistle', 'tomato', 'turquoise', 'verylightgrey', 'violet', 'violetred', 'wheat', 'whitesmoke', 'yellowgreen'];
        $dimensionsOptions = ['1 mm', '2 mm', '3 mm', '4 mm', '5 mm', '6 mm', '7 mm', '8 mm', '9 mm', '10 mm', '11 mm', '12 mm', '13 mm', '14 mm', '15 mm', '16 mm', '17 mm', '18 mm', '19 mm', '20 mm', '21 mm'];
        $sizeOptions = ['28', '29', '30', '31', '32', '33', '34', '35', '36', '37', '38', '39', '40', '41', '42', '28,5', '29,5', '30,5', '31,5', '32,5', '33,5', '34,5', '35,5', '36,5', '37,5', '38,5', '39,5', '40,5', '41,5', '42,5'];

        return [
            'color' => $colorOptions,
            'shoe-color' => $colorOptions,
            'skin' => $colorOptions,
            'shirt-color' => $colorOptions,
            'length' => $dimensionsOptions,
            'width' => $dimensionsOptions,
            'textile' => ['cotton', 'linen', 'wool', 'leather', 'silk'],
            'content' => ['1 ml', '2 ml', '3 ml', '4 ml', '5 ml', '6 ml', '7 ml', '8 ml', '9 ml', '10 ml', '11 ml', '12 ml', '13 ml', '14 ml', '15 ml', '16 ml', '17 ml', '18 ml', '19 ml', '20 ml', '21 ml'],
            'size' => $sizeOptions,
            'shoe-size' => $sizeOptions,
            'shirt-size' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL', '5XL', '6XL', '7XL', '8XL', '9XL', '10XL'],
        ];
    }
}
