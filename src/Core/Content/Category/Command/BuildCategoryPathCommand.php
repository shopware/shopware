<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Command;

use Shopware\Core\Content\Category\Util\CategoryPathBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BuildCategoryPathCommand extends Command implements EventSubscriberInterface
{
    /**
     * @var CategoryPathBuilder
     */
    private $pathBuilder;

    /**
     * @var SymfonyStyle|null
     */
    private $io;

    /**
     * @var RepositoryInterface
     */
    private $categoryRepository;

    public function __construct(CategoryPathBuilder $pathBuilder, RepositoryInterface $categoryRepository)
    {
        parent::__construct('category:build:path');
        $this->pathBuilder = $pathBuilder;
        $this->categoryRepository = $categoryRepository;
    }

    public static function getSubscribedEvents()
    {
        return [
            ProgressStartedEvent::NAME => 'startProgress',
            ProgressAdvancedEvent::NAME => 'advanceProgress',
            ProgressFinishedEvent::NAME => 'finishProgress',
        ];
    }

    public function startProgress(ProgressStartedEvent $event)
    {
        if (!$this->io) {
            return;
        }
        $this->io->comment($event->getMessage());
        $this->io->progressStart($event->getTotal());
    }

    public function finishProgress(ProgressFinishedEvent $event)
    {
        if (!$this->io) {
            return;
        }
        $this->io->progressFinish();
        $this->io->success($event->getMessage());
    }

    public function advanceProgress(ProgressAdvancedEvent $event)
    {
        if (!$this->io) {
            return;
        }
        $this->io->progressAdvance($event->getStep());
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('category:build:path')
            ->setDescription('Rebuilds the category path');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('category.parentId', null));

        $categoryResult = $this->categoryRepository->searchIds($criteria, $context);

        foreach ($categoryResult->getIds() as $categoryId) {
            $this->pathBuilder->update($categoryId, $context);
        }
    }
}
