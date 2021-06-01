<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class UserChangePasswordCommand extends Command
{
    protected static $defaultName = 'user:change-password';

    /**
     * @var EntityRepositoryInterface
     */
    private $userRepository;

    public function __construct(EntityRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED)
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'New password for the user');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $context = Context::createDefaultContext();

        $username = $input->getArgument('username');
        $password = $input->getOption('password');

        $userId = $this->getUserId($username, $context);
        if ($userId === null) {
            $io->error(sprintf('The user "%s" does not exist.', $username));

            return self::FAILURE;
        }

        if (empty($password)) {
            $passwordQuestion = new Question('New password for the user');
            $passwordQuestion->setHidden(true);
            $passwordQuestion->setMaxAttempts(3);

            $password = $io->askQuestion($passwordQuestion);
        }

        $this->userRepository->update([
            [
                'id' => $userId,
                'password' => $password,
            ],
        ], $context);

        $io->success(sprintf('The password of user "%s" has been changed successfully.', $username));

        return self::SUCCESS;
    }

    private function getUserId(string $username, Context $context): ?string
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('username', $username));

        return $this->userRepository->searchIds($criteria, $context)->firstId();
    }
}
