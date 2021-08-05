<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Content\Flow\FlowCollection;
use Shopware\Core\Content\Flow\FlowEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal API
 */
class FlowLoader extends AbstractFlowLoader
{
    private EntityRepositoryInterface $repository;

    private array $flows = [];

    public function __construct(EntityRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getDecorated(): AbstractFlowLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(string $eventName, Context $context): FlowCollection
    {
        if (\array_key_exists($eventName, $this->flows)) {
            return $this->flows[$eventName];
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('active', true),
            new EqualsFilter('eventName', $eventName),
        );
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('priority', FieldSorting::DESCENDING));

        $repositoryIterator = new RepositoryIterator($this->repository, $context, $criteria);
        $flows = new FlowCollection();
        while (($result = $repositoryIterator->fetch()) !== null) {
            /** @var FlowEntity $flow */
            foreach ($result->getEntities() as $flow) {
                if (!$flow->isInvalid() && $flow->getPayload()) {
                    $flows->add($flow);
                }
            }

            if ($result->count() < 50) {
                break;
            }
        }

        $this->flows[$eventName] = $flows;

        return $flows;
    }

    public function reset(): void
    {
        $this->flows = [];
    }
}
