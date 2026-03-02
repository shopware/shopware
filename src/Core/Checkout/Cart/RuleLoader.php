<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @final Depend on the AbstractRuleLoader which is the definition of public API for this scope
 */
#[Package('checkout')]
class RuleLoader extends AbstractRuleLoader
{
    public const TYPE_ALL = 0;
    public const TYPE_CONTEXT = 1;
    public const TYPE_FLOW = 2;

    private const LIMIT = 500;
    private const LIMIT_IDS = 10000;

    /**
     * @internal
     *
     * @param EntityRepository<RuleCollection> $repository
     */
    public function __construct(
        private readonly EntityRepository $repository
    ) {
    }

    public function getDecorated(): AbstractRuleLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(Context $context /* , int $filter = 0 */): RuleCollection
    {
        // TODO: better way for this "type" thing.
        $type = \func_num_args() >= 2 ? func_get_arg(1) : 0;
        if ($type === self::TYPE_ALL && !Feature::isActive('v6.8.0.0')) {
            $criteria = (new Criteria())
                ->addSorting(new FieldSorting('priority', FieldSorting::DESCENDING))
                ->addSorting(new FieldSorting('id'))
                ->addFilter(new EqualsFilter('invalid', false))
                ->setLimit(500)
                ->setTitle('cart-rule-loader::load-rules');

            $repositoryIterator = new RepositoryIterator($this->repository, $context, $criteria);
            $rules = new RuleCollection();

            while (($result = $repositoryIterator->fetch()) !== null) {
                foreach ($result->getEntities() as $rule) {
                    if ($rule->getPayload()) {
                        $rules->add($rule);
                    }
                }
                if ($result->count() < 500) {
                    break;
                }
            }

            return $rules;
        }

        $rules = new RuleCollection();
        // TODO: loadIds will not trigger the decoration classes at this point :/
        // Should we consider this?
        foreach (array_chunk($this->loadIds($context, $type), self::LIMIT) as $ids) {
            // No sorting or filter necessary here. Loading by id will keep the sorting
            $criteria = (new Criteria($ids))->setTitle('cart-rule-loader::load-rules');

            $result = $this->repository->search($criteria, $context);
            foreach ($result as $rule) {
                $rules->add($rule);
            }
        }

        return $rules;
    }

    /**
     * @return list<string>
     */
    public function loadIds(Context $context, int $type = 0): array
    {
        $criteria = (new Criteria())
            ->addSorting(new FieldSorting('priority', FieldSorting::DESCENDING))
            ->addSorting(new FieldSorting('id'))
            ->addFilter(new EqualsFilter('invalid', false))
            ->setLimit(self::LIMIT_IDS)
            ->setTitle('cart-rule-loader::load-rule-ids');

        $filter = $this->buildFilter($type, $context);
        if ($filter !== []) {
            $criteria->addFilter(...$filter);
        }

        $ruleIds = [];
        // Load IDs in as larges batches as possible so that the db has to filter and paginate less often (performance).
        $repositoryIterator = new RepositoryIterator($this->repository, $context, $criteria);
        while (($result = $repositoryIterator->fetchIds()) !== null) {
            /** @var list<string> $result */
            $ruleIds = array_merge($ruleIds, $result);
            if (\count($result) < self::LIMIT_IDS) {
                break;
            }
        }

        return $ruleIds;
    }

    /**
     * @return array<Filter>
     */
    private function buildFilter(int $type, Context $context): array
    {
        $filter = [];
        if ($type === self::TYPE_CONTEXT) {
            $filter = [
                new NotFilter(MultiFilter::CONNECTION_OR, [
                    new EqualsFilter('areas', null),
                    new EqualsFilter('areas', RuleAreas::FLOW_CONDITION_AREA),
                ]),
            ];
        } elseif ($type === self::TYPE_FLOW) {
            $filter = [new EqualsFilter('areas', RuleAreas::FLOW_AREA)];
        }

        $source = $context->getSource();
        if ($source instanceof SalesChannelApiSource) {
            $filter[] = new MultiFilter(MultiFilter::CONNECTION_OR, [
                new MultiFilter(MultiFilter::CONNECTION_AND, [
                    new EqualsFilter('filterBySalesChannel', true),
                    new EqualsFilter('salesChannels.id', $source->getSalesChannelId()),
                ]),
                new EqualsFilter('filterBySalesChannel', false),
            ]);
        }

        return $filter;
    }
}
