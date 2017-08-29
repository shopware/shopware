<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\Query;

use Doctrine\DBAL\Connection;

class ApiQueryQueue
{
    private $order = [];

    /**
     * @var array
     */
    private $queries = [];

    /**
     * @param ApiQuery[] ...$queries
     */
    public function __construct(ApiQuery ...$queries)
    {
        $this->queries = $queries;
    }

    public function setOrder(string ...$identifierOrder)
    {
        $this->order = $identifierOrder;

        foreach($identifierOrder as $identifier) {
            $this->queries[$identifier] = [];
        }
    }

    public function add(string $senderIdentification, ApiQuery $apiQuery) {
        if(!is_array($this->queries[$senderIdentification])) {
            throw new \InvalidArgumentException(sprintf('Unable to set query for %s, it was not beforehand registered.', $senderIdentification));
        }

        $this->queries[$senderIdentification][] = $apiQuery;
    }

    public function execute(Connection $connection)
    {
        $connection->transactional(function() use ($connection) {
            foreach($this->order as $identifier) {
                $queries = $this->queries[$identifier];

                foreach ($queries as $query) {
                    if (!$query->isExecutable()) {
                        continue;
                    }

                    $query->execute($connection);
                }
            }

            $this->queries = [];
        });
    }
}