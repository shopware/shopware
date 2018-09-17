<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\Doctrine;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Shopware\Core\Kernel;

class ConnectionFactory extends \Doctrine\Bundle\DoctrineBundle\ConnectionFactory
{
    /**
     * @var Kernel
     */
    private $kernel;

    public function __construct(array $typesConfig, Kernel $kernel)
    {
        parent::__construct($typesConfig);

        $this->kernel = $kernel;
    }

    /**
     * {@inheritdoc}
     */
    public function createConnection(
        array $params,
        Configuration $config = null,
        EventManager $eventManager = null,
        array $mappingTypes = []
    ): Connection {
        $params['pdo'] = $this->kernel::getConnection();

        // remove url from parameters as doctrine would create a new connection
        // and does not use the existing pdo connection.
        unset($params['url']);

        return parent::createConnection(
            $params,
            $config,
            $eventManager,
            $mappingTypes
        );
    }
}
