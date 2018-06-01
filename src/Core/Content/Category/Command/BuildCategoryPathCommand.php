<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Content\Category\Command;

use Shopware\Framework\Context;
use Shopware\Content\Category\CategoryRepository;
use Shopware\Content\Category\Util\CategoryPathBuilder;
use Shopware\Framework\Event\ProgressAdvancedEvent;
use Shopware\Framework\Event\ProgressFinishedEvent;
use Shopware\Framework\Event\ProgressStartedEvent;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\Query\TermQuery;
use Shopware\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BuildCategoryPathCommand extends ContainerAwareCommand implements EventSubscriberInterface
{
    /**
     * @var CategoryPathBuilder
     */
    private $pathBuilder;

    /**
     * @var SymfonyStyle
     */
    private $io;

    public function __construct(CategoryPathBuilder $pathBuilder)
    {
        parent::__construct('category:build:path');
        $this->pathBuilder = $pathBuilder;
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
            ->addOption('tenant-id', 't', InputOption::VALUE_REQUIRED, 'Tenant id')
            ->setDescription('Rebuilds the category path');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $tenantId = $input->getOption('tenant-id');

        if (!$tenantId) {
            throw new \Exception('No tenant id provided');
        }
        if (!Uuid::isValid($tenantId)) {
            throw new \Exception('Invalid uuid provided');
        }

        $context = Context::createDefaultContext($tenantId);

        $categoryRepository = $this->getContainer()->get(CategoryRepository::class);

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('category.parentId', null));

        $categoryResult = $categoryRepository->searchIds($criteria, $context);

        foreach ($categoryResult->getIds() as $categoryId) {
            $this->pathBuilder->update($categoryId, $context);
        }
    }
}
