<?php declare(strict_types=1);

namespace Shopware\Core\System\Command;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Util\Random;
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
     * @var RepositoryInterface
     */
    private $userRepository;

    public function __construct(RepositoryInterface $userRepository)
    {
        parent::__construct(null);

        $this->userRepository = $userRepository;
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

        list($accessKey, $secretAccessKey) = $this->createUser($username, $password, $tenantId);

        $io->success(sprintf('User "%s" successfully created.', $username));
        $io->table(
            ['Key', 'Value'],
            [
                ['Username', $username],
                ['Access key', $accessKey],
                ['Secret Access key', $secretAccessKey],
            ]
        );
    }

    private function userExists(string $username, string $tenantId): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('user.username', $username));

        $result = $this->userRepository->searchIds($criteria, Context::createDefaultContext($tenantId));

        return $result->getTotal() > 0;
    }

    private function createUser(string $username, string $password, string $tenantId): array
    {
        $password = password_hash($password, PASSWORD_BCRYPT);
        $accessKey = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(Random::getAlphanumericString(16)));
        $secretAccessKey = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(Random::getAlphanumericString(32)));
        $secretAccessKeyHash = hash('sha512', $secretAccessKey);
        $secretAccessKeyHash = password_hash($secretAccessKeyHash, PASSWORD_ARGON2I);

        $context = Context::createDefaultContext($tenantId);

        $this->userRepository->create([
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => $username,
                'email' => 'admin@example.com',
                'username' => $username,
                'password' => $password,
                'localeId' => Defaults::LOCALE,
                'active' => true,
                'accessKeys' => [
                    [
                        'accessKey' => $accessKey,
                        'secretAccessKey' => $secretAccessKeyHash,
                        'writeAccess' => true,
                    ],
                ],
            ],
        ], $context);

        return [$accessKey, $secretAccessKey];
    }
}
