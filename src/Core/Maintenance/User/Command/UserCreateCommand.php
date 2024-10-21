<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\User\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\User\Service\UserProvisioner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal should be used over the CLI only
 */
#[AsCommand(
    name: 'user:create',
    description: 'Creates a new user',
)]
#[Package('core')]
class UserCreateCommand extends Command
{
    public function __construct(private readonly UserProvisioner $userProvisioner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Username for the user')
            ->addOption('admin', 'a', InputOption::VALUE_NONE, 'Mark the user as admin')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Password for the user')
            ->addOption('firstName', null, InputOption::VALUE_REQUIRED, 'The user\'s firstname')
            ->addOption('lastName', null, InputOption::VALUE_REQUIRED, 'The user\'s lastname')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email for the user')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $username = $input->getArgument('username');
        $password = $input->getOption('password');

        $additionalData = [];
        $lastName = $input->getOption('lastName');
        if ($lastName) {
            $additionalData['lastName'] = $lastName;
        }

        $firstName = $input->getOption('firstName');
        if ($firstName) {
            $additionalData['firstName'] = $firstName;
        }

        $email = $input->getOption('email');
        if ($email) {
            $additionalData['email'] = $email;
        }

        if ($input->getOption('admin')) {
            $additionalData['admin'] = true;
        }

        $savedPassword = $this->userProvisioner->provision($username, $password, $additionalData);

        $message = \sprintf('User "%s" successfully created.', $username);
        if ($password === null) {
            $message .= \sprintf(' The newly generated password is: %s', $savedPassword);
            $io->warning('You didn\'t pass a password so a random one was generated. Please call "user:change-password" to set a new password.');
        }

        $io->success($message);

        return self::SUCCESS;
    }
}
