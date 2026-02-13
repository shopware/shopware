<?php declare(strict_types=1);

namespace Shopware\Core\Service\Requirement;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\State;

/**
 * @internal
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
     * Returns true if ALL requirements for the given service are met.
     *
     * Unknown requirements (not registered in platform) are treated as
     * not met for inactive services, preventing activation. However, if
     * the service is already active, unknown requirements are ignored —
     * a bad app release should not deactivate a running service. The app
     * should release a fix targeting the correct platform version.
     */
    public function isSatisfied(AppEntity $app): bool
    {
        $requirementNames = $this->getRequirements($app);
        $state = State::state($app);

        foreach ($requirementNames as $name) {
            if (!isset($this->requirements[$name])) {
                // registry return requirement that we don't have
                if ($state === State::ACTIVE) {
                    // if the app is already active, it could be that a faulty update was made
                    // let's keep it active
                    continue;
                }

                // however, if it's not already active, we can hold off
                // and wait for the app to be fixed.
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
        $requirements = $app->getSourceConfig()['requirements'] ?? [];

        if (!\is_array($requirements)) {
            return [];
        }

        /** @var list<string> $requirements */
        return $requirements;
    }
}
