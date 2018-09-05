<?php declare(strict_types=1);

namespace Shopware\Core\System\Command;

use Shopware\Core\Framework\Provisioning\UserProvisioner;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class UserCreateCommand extends Command
{
    /**
     * @var UserProvisioner
     */
    private $userProvisioner;

    public function __construct(UserProvisioner $userProvisioner)
    {
        parent::__construct();
        $this->userProvisioner = $userProvisioner;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('rest:user:create')
            ->addArgument('username', InputArgument::REQUIRED, 'Username for the user')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Password for the user')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $tenantId = $input->getOption('tenant-id');

        if (!$tenantId) {
            throw new \Exception('No tenant id provided');
        }
        if (!Uuid::isValid($tenantId)) {
            throw new \Exception('Invalid uuid provided');
        }

        $username = $input->getArgument('username');
        $password = $input->getOption('password');

        if (empty($password)) {
            $passwordQuestion = new Question('Password for the user');
            $passwordQuestion->setHidden(true);
            $passwordQuestion->setMaxAttempts(3);

            $password = $io->askQuestion($passwordQuestion);
        }

        $this->userProvisioner->provision($tenantId, $username, $password);

        $io->success(sprintf('User "%s" successfully created.', $username));
    }
}
