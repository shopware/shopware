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
        $key = Key::createNewRandomKey();
        $secret = $key->saveToAsciiSafeString();

        $newEnv = [];

        $newEnv[] = '###> symfony/lock ###';
        $newEnv[] = '# Choose one of the stores below';
        $newEnv[] = '# postgresql+advisory://db_user:db_password@localhost/db_name';
        $newEnv[] = 'LOCK_DSN=flock';
        $newEnv[] = '###< symfony/lock ###';
        $newEnv[] = '';

        $newEnv[] = '###> symfony/messenger ###';
        $newEnv[] = '# Choose one of the transports below';
        $newEnv[] = '# MESSENGER_TRANSPORT_DSN=doctrine://default';
        $newEnv[] = '# MESSENGER_TRANSPORT_DSN=amqp://guest:guest@localhost:5672/%2f/messages';
        $newEnv[] = '# MESSENGER_TRANSPORT_DSN=redis://localhost:6379/messages';
        $newEnv[] = '###< symfony/messenger ###';
        $newEnv[] = '';

        $newEnv[] = '###> symfony/mailer ###';
        $newEnv[] = 'MAILER_DSN=null://null';
        $newEnv[] = '###< symfony/mailer ###';
        $newEnv[] = '';

        $newEnv[] = '###> shopware/core ###';
        $newEnv[] = 'APP_ENV=prod';
        $newEnv[] = 'APP_SECRET=' . $secret;
        $newEnv[] = 'APP_URL=' . $shop['schema'] . '://' . $shop['host'] . $shop['basePath'];
        $newEnv[] = 'DATABASE_URL=' . $info->asDsn();
        $newEnv[] = 'DATABASE_SSL_CA=' . $info->getSslCaPath();
        $newEnv[] = 'DATABASE_SSL_CERT=' . $info->getSslCertPath();
        $newEnv[] = 'DATABASE_SSL_KEY=' . $info->getSslCertKeyPath();
        $newEnv[] = 'DATABASE_SSL_DONT_VERIFY_SERVER_CERT=' . ($info->getSslDontVerifyServerCert() ? '1' : '');
        $newEnv[] = 'COMPOSER_HOME=' . $this->projectDir . '/var/cache/composer';
        $newEnv[] = 'INSTANCE_ID=' . $this->idGenerator->getUniqueId();
        $newEnv[] = 'BLUE_GREEN_DEPLOYMENT=' . (int) $shop['blueGreenDeployment'];
        $newEnv[] = '###< shopware/core ###';
        $newEnv[] = '';

        $newEnv[] = '###> shopware/elasticsearch ###';

        if (file_exists($this->projectDir . '/symfony.lock')) {
            $newEnv[] = 'OPENSEARCH_URL=http://localhost:9200';
        } else {
            $newEnv[] = 'SHOPWARE_ES_HOSTS=http://localhost:9200';
        }

        $newEnv[] = 'SHOPWARE_ES_ENABLED=0';
        $newEnv[] = 'SHOPWARE_ES_INDEXING_ENABLED=0';
        $newEnv[] = 'SHOPWARE_ES_INDEX_PREFIX=sw';
        $newEnv[] = 'SHOPWARE_ES_THROW_EXCEPTION=1';
        $newEnv[] = '###< shopware/elasticsearch ###';
        $newEnv[] = '';

        $newEnv[] = '###> shopware/storefront ###';
        $newEnv[] = 'SHOPWARE_HTTP_CACHE_ENABLED=1';
        $newEnv[] = 'SHOPWARE_HTTP_DEFAULT_TTL=7200';
        $newEnv[] = '###< shopware/storefront ###';
        $newEnv[] = '';

        file_put_contents($this->projectDir . '/.env', implode("\n", $newEnv));

        $htaccessPath = $this->projectDir . '/public/.htaccess';

        if (file_exists($htaccessPath . '.dist') && !file_exists($htaccessPath)) {
            $perms = fileperms($htaccessPath . '.dist');
            copy($htaccessPath . '.dist', $htaccessPath);

            if ($perms) {
                chmod($htaccessPath, $perms | 0644);
            }
        }
    }
}
