<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeactivateAppCommand extends Command
{
    /**
     * @var EntityRepositoryInterface
     */
    private $appRepo;

    /**
     * @var AppStateService
     */
    private $appStateService;

    public function __construct(EntityRepositoryInterface $appRepo, AppStateService $appStateService)
    {
        parent::__construct();
        $this->appRepo = $appRepo;
        $this->appStateService = $appStateService;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $context = Context::createDefaultContext();

        /** @var string $appName */
        $appName = $input->getArgument('name');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $appName));

        $id = $this->appRepo->searchIds($criteria, $context)->firstId();

        if (!$id) {
            $io->error("No app found for \"${appName}\".");

            return 1;
        }

        $this->appStateService->deactivateApp($id, $context);
        $io->success('App deactivated successfully.');

        return 0;
    }

    protected function configure(): void
    {
        $this->setName('app:deactivate')
            ->setDescription('deactivate the app in the folder with the given name')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'The name of the app, has also to be the name of the folder under
                which the app can be found under custom/apps'
            );
    }
}
