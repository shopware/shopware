<?php declare(strict_types=1);

namespace Shopware\Recovery\Install\Struct;

use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation as CoreDatabaseConnectionInformation;
use Symfony\Component\Console\Input\InputInterface;

class DatabaseConnectionInformation extends CoreDatabaseConnectionInformation
{
    public static function fromCommandInputs(InputInterface $input): self
    {
        return (new self())->assign([
            'hostname' => $input->getOption('db-host'),
            'port' => $input->getOption('db-port'),
            'username' => $input->getOption('db-user'),
            'password' => $input->getOption('db-password'),
            'databaseName' => $input->getOption('db-name'),
            'sslCaPath' => $input->getOption('db-ssl-ca'),
            'sslCertPath' => $input->getOption('db-ssl-cert'),
            'sslCertKeyPath' => $input->getOption('db-ssl-key'),
            'sslDontVerifyServerCert' => $input->getOption('db-ssl-dont-verify-cert') === '1' ? true : false,
        ]);
    }

    public static function fromPostData(array $postData): self
    {
        return (new self())->assign([
            'username' => $postData['c_database_user'],
            'hostname' => $postData['c_database_host'],
            'port' => (int) ($postData['c_database_port'] ?? '3306'),
            'databaseName' => $postData['c_database_schema'] ?? '',
            'password' => $postData['c_database_password'],
            'sslCaPath' => $postData['c_database_ssl_ca_path'],
            'sslCertPath' => $postData['c_database_ssl_cert_path'],
            'sslCertKeyPath' => $postData['c_database_ssl_cert_key_path'],
            'sslDontVerifyServerCert' => isset($postData['c_database_ssl_dont_verify_cert']) ? true : false,
        ]);
    }
}
