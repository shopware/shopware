<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\UuidGenerator;

use Doctrine\DBAL\Connection;

class ProductNumberGenerator extends NumberGenerator
{

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        parent::__construct(
            $connection,
            'articleordernumber',
            'SW'
        );
    }
}