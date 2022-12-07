<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Facade;

use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;

/**
 * @internal
 */
class RepositoryFacadeHookFactory extends HookServiceFactory
{
    /**
     * @internal
     */
    public function __construct(
        private DefinitionInstanceRegistry $registry,
        private AppContextCreator $appContextCreator,
        private RequestCriteriaBuilder $criteriaBuilder,
        private AclCriteriaValidator $criteriaValidator
    ) {
    }

    public function factory(Hook $hook, Script $script): RepositoryFacade
    {
        return new RepositoryFacade(
            $this->registry,
            $this->criteriaBuilder,
            $this->criteriaValidator,
            $this->appContextCreator->getAppContext($hook, $script)
        );
    }

    public function getName(): string
    {
        return 'repository';
    }
}
