<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Command;

use Faker\Factory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreatePageCommand extends Command
{
    protected static $defaultName = 'cms:page:create';

    /**
     * @var EntityRepositoryInterface
     */
    private $cmsPageRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var string[]
     */
    private $products;

    /**
     * @var string[]
     */
    private $categories;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var string[]
     */
    private $media;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    public function __construct(
        EntityRepositoryInterface $cmsPageRepository,
        EntityRepositoryInterface $productRepository,
        EntityRepositoryInterface $categoryRepository,
        EntityRepositoryInterface $mediaRepository
    ) {
        parent::__construct();
        $this->cmsPageRepository = $cmsPageRepository;
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->mediaRepository = $mediaRepository;
    }

    protected function configure(): void
    {
        $this->addOption('reset', null, InputOption::VALUE_NONE, 'Reset all pages');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('reset')) {
            $this->resetPages();
        }

        $faker = Factory::create();

        $page = [
            'id' => Uuid::randomHex(),
            'name' => $faker->company,
            'type' => 'landing_page',
            'blocks' => [
                [
                    'type' => 'image-text',
                    'slots' => [
                        ['type' => 'product-box', 'slot' => 'left', 'config' => ['productId' => $this->getRandomProductId()]],
                        ['type' => 'image', 'slot' => 'right', 'config' => ['url' => $this->getRandomImageUrl()]],
                    ],
                ],
                [
                    'type' => 'image-text',
                    'slots' => [
                        ['type' => 'text', 'slot' => 'left', 'config' => ['content' => $faker->realText()]],
                        ['type' => 'product-box', 'slot' => 'right', 'config' => ['productId' => $this->getRandomProductId()]],
                    ],
                ],
                [
                    'type' => 'image-text',
                    'slots' => [
                        ['type' => 'text', 'slot' => 'right', 'config' => ['content' => $faker->realText()]],
                        ['type' => 'image', 'slot' => 'left', 'config' => ['mediaId' => $this->getRandomMediaId()]],
                    ],
                ],
                [
                    'type' => 'listing',
                    'slots' => [
                        ['type' => 'product-listing', 'slot' => 'listing', 'config' => ['categoryId' => $this->getRandomCategoryId()]],
                    ],
                ],
            ],
        ];

        $this->cmsPageRepository->create([$page], Context::createDefaultContext());

        $output->writeln('ID: ' . $page['id']);

        return self::SUCCESS;
    }

    private function resetPages(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(999);

        $context = Context::createDefaultContext();
        $pages = $this->cmsPageRepository->searchIds($criteria, $context);

        if ($pages->getTotal() === 0) {
            return;
        }

        $keys = array_map(function ($id) {
            return ['id' => $id];
        }, $pages->getIds());

        $this->cmsPageRepository->delete($keys, $context);
    }

    private function getRandomImageUrl(): string
    {
        return 'https://source.unsplash.com/random?t=' . random_int(1, 9999);
    }

    private function getRandomProductId(): string
    {
        if (empty($this->products)) {
            $criteria = new Criteria();
            $criteria->setLimit(100);

            $this->products = $this->productRepository->searchIds($criteria, Context::createDefaultContext())->getIds();
        }

        return $this->products[array_rand($this->products, 1)];
    }

    private function getRandomCategoryId(): string
    {
        if (empty($this->categories)) {
            $criteria = new Criteria();
            $criteria->setLimit(100);

            $this->categories = $this->categoryRepository->searchIds($criteria, Context::createDefaultContext())->getIds();
        }

        return $this->categories[array_rand($this->categories, 1)];
    }

    private function getRandomMediaId(): string
    {
        if (empty($this->media)) {
            $criteria = new Criteria();
            $criteria->setLimit(100);

            $this->media = $this->mediaRepository->searchIds($criteria, Context::createDefaultContext())->getIds();
        }

        return $this->media[array_rand($this->media, 1)];
    }
}
