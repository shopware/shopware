<?php

declare(strict_types=1);

/**
 * @internal
 * Polyfill for the newly introduced `Mysql` class in PHP 8.4.
 * It makes sure that we can already use the constants in older PHP versions.
 * The file can be removed once https://github.com/symfony/polyfill/pull/549 is merged
 * and a new version of `symfony/polyfill-php85` is released.
 */

namespace Pdo; /** @phpstan-ignore shopware.namespace (needs to be in the global `Pdo` namespace to function correctly) */

use Shopware\Core\Framework\Log\Package;

if (\PHP_VERSION_ID < 80400 && \extension_loaded('pdo_mysql')) {
    /**
     * @internal
     */
    #[Package('framework')]
    class Mysql extends \PDO
    {
        public const ATTR_INIT_COMMAND = \PDO::MYSQL_ATTR_INIT_COMMAND;
        public const ATTR_COMPRESS = \PDO::MYSQL_ATTR_COMPRESS;
        public const ATTR_SSL_KEY = \PDO::MYSQL_ATTR_SSL_KEY;
        public const ATTR_SSL_CERT = \PDO::MYSQL_ATTR_SSL_CERT;
        public const ATTR_SSL_CA = \PDO::MYSQL_ATTR_SSL_CA;
        public const ATTR_SSL_VERIFY_SERVER_CERT = \PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT;
    }
}
