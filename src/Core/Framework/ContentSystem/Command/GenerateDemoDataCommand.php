<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Command;

use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageContentLayoutCollection;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(
    name: 'content-system:demo:generate',
    description: 'Creates a layout-playground demo layout and assigns it to a demo landing page for a storefront sales channel.',
)]
#[Package('framework')]
class GenerateDemoDataCommand extends Command
{
    private const LANDING_PAGE_ID = '7c6c8f28f6c549ecb94a6a1760d2f5c1';

    private const LANDING_PAGE_CONTENT_LAYOUT_ID = '91f6a4c7b2664927884d4fdb00b33c4a';

    private const CONTENT_LAYOUT_ID = '22f090beeab811ad59bc5bbe3e087892';

    /**
     * @param EntityRepository<ContentLayoutCollection> $contentLayoutRepository
     * @param EntityRepository<LandingPageContentLayoutCollection> $landingPageContentLayoutRepository
     * @param EntityRepository<LandingPageCollection> $landingPageRepository
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     * @param EntityRepository<CmsPageCollection> $cmsPageRepository
     */
    public function __construct(
        private readonly EntityRepository $contentLayoutRepository,
        private readonly EntityRepository $landingPageContentLayoutRepository,
        private readonly EntityRepository $landingPageRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly EntityRepository $cmsPageRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'sales-channel-id',
            null,
            InputOption::VALUE_OPTIONAL,
            'Optional target storefront sales channel ID. If omitted, the first storefront sales channel is used.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $context = Context::createCLIContext();

        $salesChannelId = $input->getOption('sales-channel-id');
        if (!\is_string($salesChannelId) || $salesChannelId === '') {
            $salesChannelId = $this->findStorefrontSalesChannelId($context);
        }

        if ($salesChannelId === null) {
            $io->error('No storefront sales channel found. Pass one with --sales-channel-id=<id>.');

            return self::FAILURE;
        }

        $cmsPage = $this->findAnyCmsPage($context);
        if ($cmsPage === null) {
            $io->error('No CMS page found. Please create at least one CMS page and run the command again.');

            return self::FAILURE;
        }

        $this->landingPageRepository->upsert([[
            'id' => self::LANDING_PAGE_ID,
            'active' => true,
            'cmsPageId' => $cmsPage['id'],
            'cmsPageVersionId' => $cmsPage['versionId'],
            'translations' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'name' => 'Content System Layout Playground',
                'url' => 'content-system-layout-playground',
                'metaTitle' => 'Content System Layout Playground',
                'metaDescription' => 'Layout playground created by content-system:demo:generate',
            ]],
            'salesChannels' => [[
                'id' => $salesChannelId,
            ]],
        ]], $context);

        $this->contentLayoutRepository->upsert([[
            'id' => self::CONTENT_LAYOUT_ID,
            'name' => 'Content System Layout Playground',
            'version' => 'layout-playground-v1',
            'layout' => $this->buildLayout(),
        ]], $context);

        $this->landingPageContentLayoutRepository->upsert([[
            'id' => self::LANDING_PAGE_CONTENT_LAYOUT_ID,
            'landingPageId' => self::LANDING_PAGE_ID,
            'salesChannelId' => $salesChannelId,
            'contentLayoutId' => self::CONTENT_LAYOUT_ID,
        ]], $context);

        $io->success('Layout playground demo was generated.');
        $io->listing([
            'Sales channel: ' . $salesChannelId,
            'Landing page ID: ' . self::LANDING_PAGE_ID,
            'Layout ID: ' . self::CONTENT_LAYOUT_ID,
            'Store API full route: /store-api/content/landing-page/' . self::LANDING_PAGE_ID,
            'Store API skeleton route: /store-api/content-skeleton/landing-page/' . self::LANDING_PAGE_ID,
            'Storefront demo route: /content-system/demo/' . self::LANDING_PAGE_ID,
        ]);

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildLayout(): array
    {
        return [[
            'id' => 'cbb6ae0d94787ca5925a115cb2e12159',
            'component' => 'Sw:Grid',
            'properties' => [
                'columns' => '2',
            ],
            'slots' => [
                'default' => [
                    [
                        'id' => 'bae5ad73aadf677e1aaaab638332cb1c',
                        'component' => 'Sw:Content:Text',
                        'properties' => [
                            'text' => 'Content System Layout Playground',
                            'style' => 'heading',
                        ],
                    ],
                    [
                        'id' => 'f089adbc9fad44589c913aa0b8ed1417',
                        'component' => 'Sw:Grid',
                        'properties' => [
                            'columns' => '1',
                        ],
                        'slots' => [
                            'default' => [
                                [
                                    'id' => '11111111111111111111111111111111',
                                    'component' => 'Sw:Content:Text',
                                    'properties' => [
                                        'text' => 'Left column',
                                        'style' => 'heading',
                                    ],
                                ],
                                [
                                    'id' => '22222222222222222222222222222222',
                                    'component' => 'Sw:Content:Text',
                                    'properties' => [
                                        'text' => 'This column demonstrates a nested grid with reusable templates.',
                                    ],
                                ],
                                [
                                    'id' => '33333333333333333333333333333333',
                                    'component' => 'Sw:Grid',
                                    'properties' => [
                                        'columns' => '2',
                                    ],
                                    'slots' => [
                                        'default' => [
                                            [
                                                'id' => '44444444444444444444444444444444',
                                                'component' => 'Sw:Content:Text',
                                                'properties' => [
                                                    'text' => 'Nested A',
                                                    'style' => 'heading',
                                                ],
                                            ],
                                            [
                                                'id' => '55555555555555555555555555555555',
                                                'component' => 'Sw:Content:Text',
                                                'properties' => [
                                                    'text' => 'Nested B',
                                                    'style' => 'heading',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => 'a5a4a3bffd76609d1b0a36f6fcf68c45',
                        'component' => 'Sw:Grid',
                        'properties' => [
                            'columns' => '1',
                        ],
                        'slots' => [
                            'default' => [
                                [
                                    'id' => '66666666666666666666666666666666',
                                    'component' => 'Sw:Content:Text',
                                    'properties' => [
                                        'text' => 'Right column',
                                        'style' => 'heading',
                                    ],
                                ],
                                [
                                    'id' => '77777777777777777777777777777777',
                                    'component' => 'Sw:Content:Text',
                                    'properties' => [
                                        'text' => 'The same Sw:Grid template is reused at every depth level.',
                                    ],
                                ],
                                [
                                    'id' => '88888888888888888888888888888888',
                                    'component' => 'Sw:Grid',
                                    'properties' => [
                                        'columns' => '2',
                                    ],
                                    'slots' => [
                                        'default' => [
                                            [
                                                'id' => '99999999999999999999999999999999',
                                                'component' => 'Sw:Content:Text',
                                                'properties' => [
                                                    'text' => 'Card 1',
                                                    'style' => 'heading',
                                                ],
                                            ],
                                            [
                                                'id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                                                'component' => 'Sw:Grid',
                                                'properties' => [
                                                    'columns' => '1',
                                                ],
                                                'slots' => [
                                                    'default' => [
                                                        [
                                                            'id' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                                                            'component' => 'Sw:Content:Text',
                                                            'properties' => [
                                                                'text' => 'Nested inside card 2',
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]];
    }

    private function findStorefrontSalesChannelId(Context $context): ?string
    {
        $criteria = (new Criteria())->setLimit(1);
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        return $this->salesChannelRepository->searchIds($criteria, $context)->firstId();
    }

    /**
     * @return array{id: string, versionId: string}|null
     */
    private function findAnyCmsPage(Context $context): ?array
    {
        $criteria = (new Criteria())->setLimit(1);
        $cmsPage = $this->cmsPageRepository->search($criteria, $context)->first();

        if ($cmsPage === null) {
            return null;
        }

        return [
            'id' => $cmsPage->getId(),
            'versionId' => $cmsPage->getVersionId(),
        ];
    }
}
