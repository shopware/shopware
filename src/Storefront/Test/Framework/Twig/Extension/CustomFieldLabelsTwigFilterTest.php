<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Twig\Extension;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Storefront\Framework\Twig\Extension\CustomFieldLabelsTwigFilter;

class CustomFieldLabelsTwigFilterTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository
     */
    public $attributeRepository;

    /**
     * @var CustomFieldLabelsTwigFilter
     */
    public $filter;

    public function setUp(): void
    {
        $this->attributeRepository = $this->getContainer()->get('custom_field.repository');
        $this->filter = $this->getContainer()->get(CustomFieldLabelsTwigFilter::class);
    }

    public function testHappyPath(): void
    {
        $context = Context::createDefaultContext();
        $attribute = [
            [
                'name' => 'custom_field_1',
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'label' => [
                        'en-GB' => 'EN-Label-1',
                        'de-DE' => 'DE-Label-1',
                    ],
                ],
            ],

            [
                'name' => 'custom_field_2',
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'label' => [
                        'en-GB' => 'EN-Label-2',
                    ],
                ],
            ],
        ];
        $this->attributeRepository->create($attribute, $context);

        $context = Context::createDefaultContext();

        $customFieldLabels = [
            'custom_field_1' => 'EN-Label-1',
            'custom_field_2' => 'EN-Label-2',
        ];
        $customFieldNames = [
            'custom_field_1',
            'custom_field_2',
            'foobar',
        ];
        static::assertEquals($customFieldLabels, $this->filter->getCustomFieldLabels(['context' => $context], $customFieldNames));

        $chain = [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM];
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, $chain);

        $customFieldLabels = [
            'custom_field_1' => 'DE-Label-1',
            'custom_field_2' => 'EN-Label-2',
        ];
        static::assertEquals($customFieldLabels, $this->filter->getCustomFieldLabels(['context' => $context], $customFieldNames));
    }
}
