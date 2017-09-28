<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Framework\Write\Query;

use Doctrine\DBAL\Connection;

class WriteQueryQueue
{
    /**
     * @var string[]
     */
    private $registeredResources = [];

    /**
     * @var string[]
     */
    private $order = [];

    /**
     * @var array
     */
    private $queries = [];

    /**
     * @param WriteQuery[] ...$queries
     */
    public function __construct(WriteQuery ...$queries)
    {
        $this->queries = $queries;
    }

    /**
     * @param string   $resource
     * @param string[] ...$identifierOrder
     */
    public function setOrder(string $resource, string ...$identifierOrder): void
    {
        if (in_array($resource, $this->registeredResources, true)) {
            return;
        }

        $this->order = $identifierOrder;

        foreach ($identifierOrder as $identifier) {
            $this->queries[$identifier] = [];
        }

        $this->registeredResources[] = $resource;
    }

    /**
     * @param string   $resource
     * @param string[] ...$identifierOrder
     */
    public function updateOrder(string $resource, string ...$identifierOrder): void
    {
        if (in_array($resource, $this->registeredResources, true)) {
            return;
        }

        $notAlreadyOrderedIdentifiers = [];
        foreach ($identifierOrder as $identifier) {
            if ($identifier === $resource || array_search($identifier, $this->order) === false) {
                $notAlreadyOrderedIdentifiers[] = $identifier;
            }
        }

        $localIndex = array_search($resource, $this->order);
        $this->order = array_merge(
            array_slice($this->order, 0, $localIndex),
            $notAlreadyOrderedIdentifiers,
            array_slice($this->order, $localIndex + 1)
        );

        foreach ($this->order as $identifier) {
            if (isset($this->queries[$identifier])) {
                continue;
            }

            $this->queries[$identifier] = [];
        }

        $this->registeredResources[] = $resource;
    }

    /**
     * @return string[]
     */
    public function getOrder(): array
    {
        return $this->order;
    }

    public function add(string $senderIdentification, WriteQuery $apiQuery)
    {
        if (!is_array($this->queries[$senderIdentification])) {
            throw new \InvalidArgumentException(sprintf('Unable to set query for %s, it was not beforehand registered.', $senderIdentification));
        }

        $this->queries[$senderIdentification][] = $apiQuery;
    }

    public function execute(Connection $connection)
    {
        $connection->transactional(function () use ($connection) {
            foreach ($this->order as $identifier) {
                $queries = $this->queries[$identifier];

                /** @var WriteQuery $query */
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

    public function getQueries(): array
    {
        return $this->queries;
    }
}
