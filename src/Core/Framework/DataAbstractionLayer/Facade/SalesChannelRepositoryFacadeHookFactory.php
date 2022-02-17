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
 * @deprecated tag:v6.5.0 will be internal
 */
class SalesChannelRepositoryFacadeHookFactory extends HookServiceFactory
{
    private SalesChannelDefinitionInstanceRegistry $registry;

    private RequestCriteriaBuilder $criteriaBuilder;

    public function __construct(
        SalesChannelDefinitionInstanceRegistry $registry,
        RequestCriteriaBuilder $criteriaBuilder
    ) {
        $this->registry = $registry;
        $this->criteriaBuilder = $criteriaBuilder;
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
