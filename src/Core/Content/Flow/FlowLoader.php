<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Flow\SequenceTree\SequenceTree;
use Shopware\Core\Content\Flow\SequenceTree\SequenceTreeCollection;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal (FEATURE_NEXT_8225)
 */
class FlowLoader extends AbstractFlowLoader
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getDecorated(): AbstractFlowLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(string $eventName): SequenceTreeCollection
    {
        $flowPayloads = $this->connection->fetchFirstColumn(
            'SELECT `payload`  FROM `flow` WHERE `active` = 1 AND `event_name` = :eventName ORDER BY `priority` DESC;',
            ['eventName' => $eventName]
        );

        $sequenceTreeCollection = new SequenceTreeCollection();
        foreach ($flowPayloads as $payload) {
            if ($payload === null) {
                continue;
            }

            /** @var SequenceTree $sequenceTree */
            $sequenceTree = unserialize($payload);
            $sequenceTreeCollection->add($sequenceTree);
        }

        return $sequenceTreeCollection;
    }
}
