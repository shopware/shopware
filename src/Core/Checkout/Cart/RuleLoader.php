<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @final Depend on the AbstractRuleLoader which is the definition of public API for this scope
 */
#[Package('checkout')]
class RuleLoader extends AbstractRuleLoader
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $repository)
    {
    }

    public function getDecorated(): AbstractRuleLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(Context $context): RuleCollection
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('priority', FieldSorting::DESCENDING));
        $criteria->addSorting(new FieldSorting('id'));
        $criteria->setLimit(500);
        $criteria->setTitle('cart-rule-loader::load-rules');

        $repositoryIterator = new RepositoryIterator($this->repository, $context, $criteria);
        $rules = new RuleCollection();
        while (($result = $repositoryIterator->fetch()) !== null) {
            /** @var RuleEntity $rule */
            foreach ($result->getEntities() as $rule) {
                if (!$rule->isInvalid() && $rule->getPayload()) {
                    $rules->add($rule);
                }
            }
            if ($result->count() < 500) {
                break;
            }
        }

        return $rules;
    }
}
