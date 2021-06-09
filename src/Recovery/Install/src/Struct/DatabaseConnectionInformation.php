<?php declare(strict_types=1);

namespace Shopware\Recovery\Install\Struct;

class DatabaseConnectionInformation extends Struct
{
    public ?string $hostname = '';

    public ?string $port = '';

    public ?string $socket = '';

    public ?string $username = '';

    public ?string $password;

    public ?string $databaseName = '';

    public ?string $sslCaPath = null;

    public ?string $sslCertPath = null;

    public ?string $sslCertKeyPath = null;

    public ?bool $sslDontVerifyServerCert = null;
}
