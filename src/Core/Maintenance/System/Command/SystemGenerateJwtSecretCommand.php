<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Command;

use Shopware\Core\Maintenance\System\Service\JwtCertificateGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal should be used over the CLI only
 */
class SystemGenerateJwtSecretCommand extends Command
{
    public static $defaultName = 'system:generate-jwt-secret';

    private string $projectDir;

    private JwtCertificateGenerator $jwtCertificateGenerator;

    public function __construct(string $projectDir, JwtCertificateGenerator $jwtCertificateGenerator)
    {
        parent::__construct();
        $this->projectDir = $projectDir;
        $this->jwtCertificateGenerator = $jwtCertificateGenerator;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!\extension_loaded('openssl')) {
            $io->error('extension openssl is required');

            return self::FAILURE;
        }

        $passphrase = $input->getOption('jwt-passphrase');
        $privateKeyPathOption = $input->getOption('private-key-path');

        $privateKeyPath = $privateKeyPathOption ?? ($this->projectDir . '/config/jwt/private.pem');

        $publicKeyPath = $input->getOption('public-key-path');

        if (!$publicKeyPath && !$input->getOption('private-key-path')) {
            $publicKeyPath = $this->projectDir . '/config/jwt/public.pem';
        }

        $force = $input->getOption('force');

        if (!\is_string($privateKeyPath)) {
            $io->error('Private key path is invalid');

            return self::FAILURE;
        }

        if (file_exists($privateKeyPath) && !$force) {
            $io->error(sprintf('Cannot create private key %s, it already exists.', $privateKeyPath));

            return self::FAILURE;
        }

        if (!\is_string($publicKeyPath)) {
            $io->error('Public key path is invalid');

            return self::FAILURE;
        }

        if (file_exists($publicKeyPath) && !$force) {
            $io->error(sprintf('Cannot create public key %s, it already exists.', $publicKeyPath));

            return self::FAILURE;
        }

        if (!\is_string($passphrase)) {
            $io->error('Passphrase is invalid');

            return self::FAILURE;
        }

        $this->jwtCertificateGenerator->generate($privateKeyPath, $publicKeyPath, $passphrase);

        return 0;
    }

    protected function configure(): void
    {
        $this->addOption('private-key-path', null, InputOption::VALUE_OPTIONAL, 'JWT public key path')
            ->addOption('public-key-path', null, InputOption::VALUE_OPTIONAL, 'JWT public key path')
            ->addOption('jwt-passphrase', null, InputOption::VALUE_OPTIONAL, 'JWT private key passphrase', 'shopware')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force recreation')
        ;
    }
}
