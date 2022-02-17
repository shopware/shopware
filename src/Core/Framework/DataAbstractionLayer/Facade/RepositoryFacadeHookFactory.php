<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Facade;

use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;

/**
 * @deprecated tag:v6.5.0 will be internal
 */
class RepositoryFacadeHookFactory extends HookServiceFactory
{
    private DefinitionInstanceRegistry $registry;

    private RequestCriteriaBuilder $criteriaBuilder;

    private AclCriteriaValidator $criteriaValidator;

    private AppContextCreator $appContextCreator;

    public function __construct(
        DefinitionInstanceRegistry $registry,
        AppContextCreator $appContextCreator,
        RequestCriteriaBuilder $criteriaBuilder,
        AclCriteriaValidator $criteriaValidator
    ) {
        $this->registry = $registry;
        $this->appContextCreator = $appContextCreator;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->criteriaValidator = $criteriaValidator;
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
