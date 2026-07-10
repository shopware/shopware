<?php declare(strict_types=1);

namespace Shopware\Core\Service\Requirement;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\ServiceSourceResolver;

/**
 * @internal
 *
 * @phpstan-import-type ServiceSourceConfig from ServiceSourceResolver
 */
#[Package('framework')]
class RequirementsValidator
{
    /**
     * @var array<string, ServiceRequirement>
     */
    private readonly array $requirements;

    /**
     * @param iterable<string, ServiceRequirement> $requirements
     */
    public function __construct(iterable $requirements)
    {
        $this->requirements = \iterator_to_array($requirements);
    }

    /**
     * @param list<string> $requirements
     */
    public function isValidSet(array $requirements): bool
    {
        foreach ($requirements as $requirement) {
            if (!isset($this->requirements[$requirement])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns true only if all requirements for the given service are satisfied.
     *
     * Unknown requirements are treated as unsatisfied; however, we already check that in ServiceLifecycle::install/update
     * so this code path should never execute.
     */
    public function isSatisfied(AppEntity $app): bool
    {
        $requirementNames = $this->getRequirements($app);

        foreach ($requirementNames as $name) {
            if (!isset($this->requirements[$name])) {
                return false;
            }

            if (!$this->requirements[$name]->isSatisfied()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return non-empty-list<string>
     */
    private function getRequirements(AppEntity $app): array
    {
        /** @var ServiceSourceConfig $sourceConfig */
        $sourceConfig = $app->getSourceConfig();

        return $sourceConfig['requirements'];
    }
}
