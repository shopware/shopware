<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\User\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * @internal should be used over the CLI only
 */
#[AsCommand(
    name: 'user:change-password',
    description: 'Change the password of a user',
)]
#[Package('core')]
class UserChangePasswordCommand extends Command
{
    public function __construct(private readonly EntityRepository $userRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED)
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'New password for the user')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $context = Context::createDefaultContext();

        $username = $input->getArgument('username');
        $password = $input->getOption('password');

        if (!$password) {
            $passwordQuestion = new Question('Enter new password for user');
            $passwordQuestion->setValidator(static function ($value): string {
                if ($value === null || trim($value) === '') {
                    throw new \RuntimeException('The password cannot be empty');
                }

                return $value;
            });
            $passwordQuestion->setHidden(true);
            $passwordQuestion->setMaxAttempts(3);

            $password = $io->askQuestion($passwordQuestion);
        }

        $userId = $this->getUserId($username, $context);
        if ($userId === null) {
            $io->error(sprintf('The user "%s" does not exist.', $username));

            return self::FAILURE;
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
