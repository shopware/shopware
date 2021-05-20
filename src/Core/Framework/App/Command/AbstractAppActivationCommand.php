<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
abstract class AbstractAppActivationCommand extends Command
{
    /**
     * @var EntityRepositoryInterface
     */
    protected $appRepo;

    /**
     * @var string
     */
    private $action;

    public function __construct(EntityRepositoryInterface $appRepo, string $action)
    {
        $this->appRepo = $appRepo;
        $this->action = $action;

        parent::__construct();
    }

    abstract public function runAction(string $appId, Context $context): void;

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $context = Context::createDefaultContext();

        $appName = $input->getArgument('name');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $appName));

        $id = $this->appRepo->searchIds($criteria, $context)->firstId();

        if (!$id) {
            $io->error("No app found for \"${appName}\".");

            return self::FAILURE;
        }

        $this->runAction($id, $context);

        $io->success(sprintf('App %sd successfully.', $this->action));

        return self::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setDescription($this->action . ' the app in the folder with the given name')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'The name of the app, has also to be the name of the folder under
                which the app can be found under custom/apps'
            );
    }
}
