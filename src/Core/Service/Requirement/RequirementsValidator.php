<?php declare(strict_types=1);

namespace Shopware\Core\Service\Requirement;

use Shopware\Core\Framework\Log\Package;

/**
 * Evaluates a service's requirements for a given {@see Gate}: are all of that gate's requirements
 * currently met? The Installation gate decides whether a service may exist (install/uninstall); the
 * Privileges gate decides whether an installed service may run (privileges granted/revoked).
 *
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
     * True only if every requirement of the given gate among the names is satisfied. An unknown
     * requirement name is treated as unsatisfied (a service declaring something we don't model never
     * passes any gate).
     *
     * @param list<string> $requirementNames
     */
    public function isSatisfied(array $requirementNames, Gate $gate): bool
    {
        foreach ($requirementNames as $name) {
            $requirement = $this->requirements[$name] ?? null;

            if ($requirement === null) {
                return false;
            }

            if ($requirement->getGate() === $gate && !$requirement->isSatisfied()) {
                return false;
            }
        }

        return true;
    }
}
