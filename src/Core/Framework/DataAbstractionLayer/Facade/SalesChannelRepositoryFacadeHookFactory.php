<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Facade;

use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Script\Exception\HookInjectionException;
use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;

/**
 * @internal
 */
class SalesChannelRepositoryFacadeHookFactory extends HookServiceFactory
{
    /**
     * @internal
     */
    public function __construct(
        private SalesChannelDefinitionInstanceRegistry $registry,
        private RequestCriteriaBuilder $criteriaBuilder
    ) {
    }

    public function factory(Hook $hook, Script $script): SalesChannelRepositoryFacade
    {
        if (!$hook instanceof SalesChannelContextAware) {
            throw new HookInjectionException($hook, self::class, SalesChannelContextAware::class);
        }

        return new SalesChannelRepositoryFacade(
            $this->registry,
            $this->criteriaBuilder,
            $hook->getSalesChannelContext()
        );
    }

    public function getName(): string
    {
        return 'store';
    }
}
