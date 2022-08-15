<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Configuration;

use Defuse\Crypto\Key;
use Shopware\Core\Installer\Controller\ShopConfigurationController;
use Shopware\Core\Installer\Finish\UniqueIdGenerator;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;

/**
 * @internal
 *
 * @phpstan-import-type Shop from ShopConfigurationController
 */
class EnvConfigWriter
{
    private string $projectDir;

    private UniqueIdGenerator $idGenerator;

    public function __construct(string $projectDir, UniqueIdGenerator $idGenerator)
    {
        $this->projectDir = $projectDir;
        $this->idGenerator = $idGenerator;
    }

    /**
     * @param Shop $shop
     */
    public function writeConfig(DatabaseConnectionInformation $info, array $shop): void
    {
        $tpl = '# This file is a "template" of which env vars need to be defined for your application
# Copy this file to .env file for development, create environment variables when deploying to production
# https://symfony.com/doc/current/best_practices/configuration.html#infrastructure-related-configuration

###> symfony/framework-bundle ###
%s
#TRUSTED_PROXIES=127.0.0.1,127.0.0.2
#TRUSTED_HOSTS=localhost,example.com
###< symfony/framework-bundle ###

###> symfony/swiftmailer-bundle ###
# For Gmail as a transport, use: "gmail://username:password@localhost"
# For a generic SMTP server, use: "smtp://localhost:25?encryption=&auth_mode="
# Delivery is disabled by default via "null://localhost"
MAILER_URL=null://localhost
###< symfony/swiftmailer-bundle ###

%s
';
        $key = Key::createNewRandomKey();
        $secret = $key->saveToAsciiSafeString();

        $appEnvVars = array_filter([
            'APP_ENV' => 'prod',
            'APP_SECRET' => $secret,
            'APP_URL' => $shop['schema'] . '://' . $shop['host'] . $shop['basePath'],
            'DATABASE_SSL_CA' => $info->getSslCaPath(),
            'DATABASE_SSL_CERT' => $info->getSslCertPath(),
            'DATABASE_SSL_KEY' => $info->getSslCertKeyPath(),
            'DATABASE_SSL_DONT_VERIFY_SERVER_CERT' => $info->getSslDontVerifyServerCert() ? '1' : '',
        ]);

        $additionalEnvVars = [
            'DATABASE_URL' => $info->asDsn(),
            'COMPOSER_HOME' => $this->projectDir . '/var/cache/composer',
            'INSTANCE_ID' => $this->idGenerator->getUniqueId(),
            'BLUE_GREEN_DEPLOYMENT' => (int) $shop['blueGreenDeployment'],
            'SHOPWARE_HTTP_CACHE_ENABLED' => '1',
            'SHOPWARE_HTTP_DEFAULT_TTL' => '7200',
            'SHOPWARE_ES_HOSTS' => '',
            'SHOPWARE_ES_ENABLED' => '0',
            'SHOPWARE_ES_INDEXING_ENABLED' => '0',
            'SHOPWARE_ES_INDEX_PREFIX' => 'sw',
            'SHOPWARE_CDN_STRATEGY_DEFAULT' => 'id',
        ];

        $envFile = sprintf(
            $tpl,
            $this->toEnv($appEnvVars),
            $this->toEnv($additionalEnvVars)
        );

        file_put_contents($this->projectDir . '/.env', $envFile);

        $htaccessPath = $this->projectDir . '/public/.htaccess';

        if (file_exists($htaccessPath . '.dist') && !file_exists($htaccessPath)) {
            $perms = fileperms($htaccessPath . '.dist');
            copy($htaccessPath . '.dist', $htaccessPath);

            if ($perms) {
                chmod($htaccessPath, $perms | 0644);
            }
        }
    }

    /**
     * @param array<string, string|int> $keyValuePairs
     */
    private function toEnv(array $keyValuePairs): string
    {
        $lines = [];

        foreach ($keyValuePairs as $key => $value) {
            $lines[] = $key . '="' . $value . '"';
        }

        return implode(\PHP_EOL, $lines);
    }
}
