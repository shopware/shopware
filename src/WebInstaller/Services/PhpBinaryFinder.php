<?php
declare(strict_types=1);

namespace Shopware\WebInstaller\Services;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('core')]
class PhpBinaryFinder
{
    private const PHP_BINARY_NAMES = ['php8.3', 'php8.2', 'php8.1', 'php'];

    private const PHP_KNOWN_LOCATIONS = [
        '/opt/plesk/php/{major}.{minor}/bin/php',
        '/bin/php{major}{minor}',
        '/opt/RZphp{major}{minor}/bin/php-cli',
        '/opt/alt/php{major}{minor}/usr/bin/php',
        '/opt/php-{major}.{minor}.{release}/bin/php',
        '/opt/php-{major}.{minor}/bin/php',
        '/opt/php{major}.{minor}/bin/php',
        '/opt/php{major}{minor}/bin/php',
        '/opt/php{major}/bin/php',
        '/usr/bin/php{major}.{minor}-cli',
        '/usr/bin/php{major}.{minor}',
        '/usr/bin/php{major}{minor}',
        '/usr/bin/php{major}{minor}/php{major}',
        '/usr/bin/php{major}',
        '/usr/iports/php{major}{minor}/bin/php',
        '/usr/lib/cgi-bin/php{major}.{minor}',
        '/usr/lib64/php{major}.{minor}/bin/php',
        '/usr/local/bin/edis-php-cli-{major}{minor}-stable-openssl',
        '/usr/local/bin/edis-php-cli-{major}{minor}',
        '/usr/local/bin/php{major}-{major}{minor}LATEST-CLI',
        '/usr/local/bin/php{major}.{minor}.{release}-cli',
        '/usr/local/php-{major}.{minor}/bin/php',
        '/usr/local/php{major}{minor}/bin/php',
        '/usr/local/php{major}.{minor}/bin/php',
        '/usr/local/phpfarm/inst/php-{major}.{minor}/bin/php',
        '/usr/local/php{major}{minor}/bin/php',
        '/opt/phpbrew/php/php-{major}.{minor}/bin/php',
        '/opt/phpfarm/inst/php-{major}.{minor}/bin/php-cgi',
        '/vrmd/webserver/php{major}{minor}/bin/php',
        '/package/host/localhost/php-{major}.{minor}/bin/php',
        '/Applications/MAMP/bin/php/php{major}.{minor}.{release}/bin/php',
        '/usr/local/bin/php_cli',
        '/usr/local/bin/php',
        '/usr/bin/php',
    ];

    public function find(): string
    {
        // Look for specific PHP binaries by hosters
        if ($hosterSpecificBinary = $this->findHostedSpecificBinary()) {
            return $hosterSpecificBinary;
        }

        // Look for PHP binaries in same place as our fpm/cgi binary
        if (\defined('PHP_BINARY')) {
            $phpPath = \dirname(\PHP_BINARY);
            $fileName = explode('-', basename(\PHP_BINARY), 2);
            $expectedPath = $phpPath . \DIRECTORY_SEPARATOR . $fileName[0];

            if ($this->isPHPRunning($expectedPath)) {
                return $expectedPath;
            }
        }

        // Look into PHP path
        $finder = new ExecutableFinder();

        foreach (self::PHP_BINARY_NAMES as $name) {
            $binary = $finder->find($name);

            if ($binary !== null) {
                return $binary;
            }
        }

        return '';
    }

    private function findHostedSpecificBinary(): ?string
    {
        foreach (self::PHP_KNOWN_LOCATIONS as $knownLocation) {
            $path = $this->getPhpVersionPath($knownLocation);

            if ($this->isPHPRunning($path)) {
                return $path;
            }
        }

        return null;
    }

    private function getPhpVersionPath(string $path): string
    {
        return str_replace(
            [
                '{major}',
                '{minor}',
                '{release}',
                '{extra}',
            ],
            [
                \PHP_MAJOR_VERSION,
                \PHP_MINOR_VERSION,
                \PHP_RELEASE_VERSION,
                \PHP_EXTRA_VERSION,
            ],
            $path
        );
    }

    private function isPHPRunning(string $path): bool
    {
        $process = new Process([$path, '-v']);

        try {
            $process->run();
        } catch (ProcessSignaledException) {
            return false;
        }

        return $process->isSuccessful();
    }
}
