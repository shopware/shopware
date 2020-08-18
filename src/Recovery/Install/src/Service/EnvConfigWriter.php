<?php declare(strict_types=1);

namespace Shopware\Recovery\Install\Service;

use Defuse\Crypto\Key;
use Shopware\Recovery\Install\Struct\DatabaseConnectionInformation;
use Shopware\Recovery\Install\Struct\Shop;

class EnvConfigWriter
{
    /**
     * @var string
     */
    private $configPath;

    /**
     * @var string
     */
    private $instanceId;

    public function __construct(string $configPath, string $instanceId)
    {
        $this->configPath = $configPath;
        $this->instanceId = $instanceId;
    }

    public function writeConfig(DatabaseConnectionInformation $info, Shop $shop): void
    {
        $tpl = '# This file is a "template" of which env vars need to be defined for your application
# Copy this file to .env file for development, create environment variables when deploying to production
# https://symfony.com/doc/current/best_practices/configuration.html#infrastructure-related-configuration

###> symfony/framework-bundle ###
APP_ENV=prod
APP_SECRET=%s
APP_URL=%s
#TRUSTED_PROXIES=127.0.0.1,127.0.0.2
#TRUSTED_HOSTS=localhost,example.com
###< symfony/framework-bundle ###

###> symfony/swiftmailer-bundle ###
# For Gmail as a transport, use: "gmail://username:password@localhost"
# For a generic SMTP server, use: "smtp://localhost:25?encryption=&auth_mode="
# Delivery is disabled by default via "null://localhost"
MAILER_URL=null://localhost
###< symfony/swiftmailer-bundle ###

DATABASE_URL=%s

COMPOSER_HOME=%s

INSTANCE_ID=%s

BLUE_GREEN_DEPLOYMENT=1

SHOPWARE_HTTP_CACHE_ENABLED=1
SHOPWARE_HTTP_DEFAULT_TTL=7200
SHOPWARE_ES_HOSTS=""
SHOPWARE_ES_ENABLED="0"
SHOPWARE_ES_INDEXING_ENABLED="0"
SHOPWARE_ES_INDEX_PREFIX="sw"

SHOPWARE_CDN_STRATEGY_DEFAULT="id"
';

        $key = Key::createNewRandomKey();
        $secret = $key->saveToAsciiSafeString();
        $dbUrl = sprintf(
            'mysql://%s:%s@%s:%s/%s',
            rawurlencode($info->username),
            rawurlencode($info->password),
            rawurlencode($info->hostname),
            rawurlencode((string) $info->port),
            rawurlencode($info->databaseName)
        );

        $envFile = sprintf(
            $tpl,
            $secret,
            'http://' . $shop->host . $shop->basePath,
            $dbUrl,
            SW_PATH . '/var/cache/composer',
            $this->instanceId
        );

        file_put_contents($this->configPath, $envFile);

        $htaccessPath = SW_PATH . '/public/.htaccess';

        if (file_exists($htaccessPath . '.dist') && !file_exists($htaccessPath)) {
            $perms = fileperms($htaccessPath . '.dist');
            copy($htaccessPath . '.dist', $htaccessPath);

            if ($perms) {
                chmod($htaccessPath, $perms | 0644);
            }
        }
    }
}
