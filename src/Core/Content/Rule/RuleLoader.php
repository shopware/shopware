<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Rule\Rule;

class RuleLoader
{
    /**
     * @var array<string, Rule>|null
     */
    private ?array $rules = null;

    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Returns all active+valid rules, indexed by their id and unserialized
     *
     * @return array<string, Rule>
     */
    public function load(): array
    {
        if ($this->rules !== null) {
            return $this->rules;
        }

        $payloads = $this->connection->fetchAllKeyValue('SELECT LOWER(HEX(id)), payload FROM rule WHERE invalid = 0 ORDER BY priority DESC');

        $rules = [];
        foreach ($payloads as $id => $payload) {
            try {
                $rules[$id] = unserialize($payload);
            } catch (\Throwable $e) {
                $this->logger->error('Could not unserialize rule', ['id' => $id, 'error' => $e->getMessage()]);
            }
        }

        return $this->rules = $rules;
    }
}
