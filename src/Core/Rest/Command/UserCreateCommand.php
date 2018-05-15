<?php declare(strict_types=1);

namespace Shopware\Rest\Command;

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\System\User\Repository\UserRepository;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Struct\Uuid;
use Shopware\Framework\Util\Random;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\User\User;

class UserCreateCommand extends Command
{
    /**
     * @var EncoderFactoryInterface
     */
    private $encoderFactory;

    /**
     * @var UserRepository
     */
    private $userRepository;

    public function __construct(UserRepository $userRepository, EncoderFactoryInterface $encoderFactory)
    {
        parent::__construct(null);

        $this->userRepository = $userRepository;
        $this->encoderFactory = $encoderFactory;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('rest:user:create')
            ->addArgument('username', InputArgument::REQUIRED, 'Username for the user')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Password for the user')
            ->addOption('tenant-id', 't', InputOption::VALUE_REQUIRED, 'Tenant id')
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

        if ($this->userExists($username, $tenantId)) {
            $io->error(sprintf('User with username "%s" already exists.', $username));
            exit(1);
        }

        $accessKey = $this->createUser($username, $password, $tenantId);

        $io->success(sprintf('User "%s" successfully created.', $username));
        $io->table(
            ['Key', 'Value'],
            [
                ['Username', $username],
                ['Access key', $accessKey],
            ]
        );
    }

    private function userExists(string $username, string $tenantId): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('user.username', $username));

        $result = $this->userRepository->searchIds($criteria, ApplicationContext::createDefaultContext($tenantId));

        return $result->getTotal() > 0;
    }

    private function createUser(string $username, string $password, string $tenantId): string
    {
        $password = password_hash($password, PASSWORD_BCRYPT);
        $accessKey = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(Random::getAlphanumericString(32)));

        $context = ApplicationContext::createDefaultContext($tenantId);

        $this->userRepository->create([
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => $username,
                'email' => 'admin@example.com',
                'username' => $username,
                'password' => $password,
                'localeId' => '7b52d9dd2b0640ec90be9f57edf29be7',
                'roleId' => '7b52d9dd2b0640ec90be9f57edf29be7',
                'active' => true,
                'apiKey' => $accessKey,
            ],
        ], $context);

        return $accessKey;
    }
}
