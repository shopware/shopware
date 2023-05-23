<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\System\Service\JwtCertificateGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal should be used over the CLI only
 */
#[AsCommand(
    name: 'system:generate-jwt-secret',
    description: 'Generates a new JWT secret',
)]
#[Package('core')]
class SystemGenerateJwtSecretCommand extends Command
{
    public function __construct(
        private readonly string $projectDir,
        private readonly JwtCertificateGenerator $jwtCertificateGenerator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('private-key-path', null, InputOption::VALUE_OPTIONAL, 'JWT public key path')
            ->addOption('public-key-path', null, InputOption::VALUE_OPTIONAL, 'JWT public key path')
            ->addOption('jwt-passphrase', null, InputOption::VALUE_OPTIONAL, 'JWT private key passphrase', 'shopware')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force recreation')
            ->addOption('use-env', null, InputOption::VALUE_NONE, 'Print JWT secret to console to use it as environment variable')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $passphrase = $input->getOption('jwt-passphrase');

        $privateKeyPath = $input->getOption('private-key-path') ?? ($this->projectDir . '/config/jwt/private.pem');
        $publicKeyPath = $input->getOption('public-key-path') ?? ($this->projectDir . '/config/jwt/public.pem');

        if (!\is_string($passphrase)) {
            $io->error('Passphrase is invalid');

            return self::FAILURE;
        }

        if ($input->getOption('use-env')) {
            [$private, $public] = $this->jwtCertificateGenerator->generateString($passphrase);

            if ($output instanceof ConsoleOutputInterface) {
                $errorIo = new SymfonyStyle($input, $output->getErrorOutput());

                $errorIo->info('Add these two environment variables to your .env file');
            }

            $io->writeln('JWT_PUBLIC_KEY=' . base64_encode($public));
            $io->writeln('JWT_PRIVATE_KEY=' . base64_encode($private));
            $io->writeln('');

            if ($output instanceof ConsoleOutputInterface) {
                $errorIo = new SymfonyStyle($input, $output->getErrorOutput());

                $errorIo->info('Make sure that you have configured in config/packages/shopware.yaml the following to load the JWT keys over environment variables:');
                $errorIo->block(
                    <<<YAML
    shopware:
        api:
            jwt_key:
                private_key_path: '%env(base64:JWT_PRIVATE_KEY)%'
                public_key_path: '%env(base64:JWT_PUBLIC_KEY)%'
    YAML
                );
            }

            return Command::SUCCESS;
        }

        $force = $input->getOption('force');

        if (!\is_string($privateKeyPath)) {
            $io->error('Private key path is invalid');

            return self::FAILURE;
        }

        if (!\is_string($publicKeyPath)) {
            $io->error('Public key path is invalid');

            return self::FAILURE;
        }

        if (file_exists($privateKeyPath) && !$force) {
            $io->error(sprintf('Cannot create private key %s, it already exists.', $privateKeyPath));

            return self::FAILURE;
        }

        if (file_exists($publicKeyPath) && !$force) {
            $io->error(sprintf('Cannot create public key %s, it already exists.', $publicKeyPath));

            return self::FAILURE;
        }

        $this->jwtCertificateGenerator->generate($privateKeyPath, $publicKeyPath, $passphrase);

        return Command::SUCCESS;
    }
}
