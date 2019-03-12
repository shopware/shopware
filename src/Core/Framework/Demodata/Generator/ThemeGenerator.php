<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTheme\SalesChannelThemeDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Storefront\Framework\Theme\ThemeDefinition;

class ThemeGenerator implements DemodataGeneratorInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $themeRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelThemeRepository;

    public function __construct(
        EntityRepositoryInterface $themeRepository,
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $salesChannelThemeRepository
    ) {
        $this->themeRepository = $themeRepository;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->salesChannelThemeRepository = $salesChannelThemeRepository;
    }

    public function getDefinition(): string
    {
        return ThemeDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $context->getConsole()->progressStart($numberOfItems);

        $payload = [];
        $values = $this->getValues();
        $config = $this->getConfig();

        for ($i = 0; $i < $numberOfItems; ++$i) {
            $payload[] = [
                'id' => Uuid::uuid4()->getHex(),
                'name' => $context->getFaker()->name,
                'author' => $context->getFaker()->name,
                'config' => $config,
                'values' => $values,
            ];
            $values = null;
        }

        foreach (array_chunk($payload, 100) as $chunk) {
            $this->themeRepository->upsert($chunk, $context->getContext());
            $context->getConsole()->progressAdvance(\count($chunk));
        }

        $context->getConsole()->progressFinish();
        $context->add(ThemeDefinition::class, ...array_column($payload, 'id'));

        $this->bindThemeToSalesChannel($context);
    }

    public function bindThemeToSalesChannel(DemodataContext $context): void
    {
        $payload[] = [
            'salesChannelId' => $this->getRandomSalesChannelId($context),
            'themeId' => $this->getRandomThemeId($context),
        ];

        foreach (array_chunk($payload, 100) as $chunk) {
            $this->salesChannelThemeRepository->upsert($chunk, $context->getContext());
        }

        $context->add(SalesChannelThemeDefinition::class, ...array_column($payload, 'theme_id'));
    }

    public function getConfig(): array
    {
        return [
            'colors' => [
                'generelColors' => [
                    'label' => 'Generel color',
                    'fields' => [
                        'textColor' => [
                            'label' => 'Textcolor',
                            'type' => 'colorpicker',
                        ],
                        'surface' => [
                            'label' => 'Surface',
                            'type' => 'colorpicker',
                        ],
                        'frame' => [
                            'label' => 'Frame',
                            'type' => 'colorpicker',
                        ],
                    ],
                ],
                'moreColors' => [
                    'label' => 'More color',
                    'fields' => [
                        'additionalColor' => [
                            'label' => 'Unimportant Color',
                            'type' => 'colorpicker',
                        ],
                        'additionalColor2' => [
                            'label' => 'This Color will make your page Sick!',
                            'type' => 'colorpicker',
                        ],
                    ],
                ],
            ],
            'fonts' => [
                'fonts' => [
                    'label' => 'Fonts',
                    'fields' => [
                        'headlines' => [
                            'label' => 'Headlines',
                            'type' => 'select',
                        ],
                        'text' => [
                            'label' => 'Text',
                            'type' => 'select',
                        ],
                    ],
                ],
            ],
            'links' => [
                'sociallinks' => [
                    'label' => 'Social Media Links',
                    'fields' => [
                        'facebook' => [
                            'label' => 'Facebook',
                            'type' => 'url',
                        ],
                        'twitter' => [
                            'label' => 'Twitter',
                            'type' => 'url',
                        ],
                    ],
                ],
            ],
            'media' => [
                'media' => [
                    'label' => 'Media',
                    'fields' => [
                        'image1' => [
                            'label' => 'Image 1',
                            'type' => 'media',
                        ],
                        'image2' => [
                            'label' => 'Image 2',
                            'type' => 'media',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getValues(): array
    {
        return [
            'colors' => [
                'generelColors' => [
                    'fields' => [
                        'textColor' => [
                            'value' => '#000',
                        ],
                        'surface' => [
                            'value' => '#fff',
                        ],
                        'frame' => [
                            'value' => '#000',
                        ],
                    ],
                ],
                'moreColors' => [
                    'fields' => [
                        'additionalColor' => [
                            'value' => '#696',
                        ],
                        'additionalColor2' => [
                            'value' => '#969',
                        ],
                    ],
                ],
            ],
            'fonts' => [
                'fonts' => [
                    'fields' => [
                        'headlines' => [
                            'value' => '',
                        ],
                        'text' => [
                            'value' => '',
                        ],
                    ],
                ],
            ],
            'links' => [
                'sociallinks' => [
                    'fields' => [
                        'facebook' => [
                            'value' => 'https://www.facebook.com/shopware/',
                        ],
                        'twitter' => [
                            'value' => 'https://twitter.com/shopware',
                        ],
                    ],
                ],
            ],
            'media' => [
                'media' => [
                    'fields' => [
                        'image1' => [
                            'value' => '',
                        ],
                        'image2' => [
                            'value' => '',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getRandomThemeId(DemodataContext $context)
    {
        if ($themeId = $context->getRandomId(ThemeDefinition::class)) {
            return $themeId;
        }

        $criteria = new Criteria();
        $criteria->setLimit(1);

        return $this->themeRepository->searchIds($criteria, $context->getContext())->getIds()[0];
    }

    private function getRandomSalesChannelId(DemodataContext $context)
    {
        if ($salesChannelId = $context->getRandomId(SalesChannelDefinition::class)) {
            return $salesChannelId;
        }

        $criteria = new Criteria();
        $criteria->setLimit(1);

        return $this->salesChannelRepository->searchIds($criteria, $context->getContext())->getIds()[0];
    }
}
