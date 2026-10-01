<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Requirements\Requirement;
use Shopware\Core\Framework\App\Validation\Requirements\UnmetRequirement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class AppRequirementsValidator
{
    /**
     * @param iterable<Requirement> $validators
     */
    public function __construct(
        private readonly iterable $validators,
        private readonly LoggerInterface $logger,
        private readonly string $environment = 'prod',
    ) {
    }

    /**
     * Requirements are only enforced in the 'prod' environment.
     * In dev/test, validation is skipped so local development and CI are not blocked
     * by infrastructure checks (HTTPS, public reachability, etc.).
     *
     * @return array<UnmetRequirement>
     */
    public function validate(Manifest $manifest): array
    {
        $this->logUnknownRequirements($manifest);

        if ($this->environment !== 'prod') {
            return [];
        }

        $validationErrors = [];
        foreach ($this->validators as $validator) {
            if (!$validator->required($manifest)) {
                continue;
            }

            $unmet = $validator->validate($manifest);
            if ($unmet !== null) {
                $validationErrors[] = $unmet;
            }
        }

        return $validationErrors;
    }

    private function logUnknownRequirements(Manifest $manifest): void
    {
        $supportedRequirements = array_map(
            static fn (Requirement $requirement) => $requirement::name(),
            iterator_to_array($this->validators)
        );

        $invalidRequirements = array_unique(array_diff($manifest->getRequirements(), $supportedRequirements));

        foreach ($invalidRequirements as $requirementName) {
            $this->logger->warning(
                'App manifest declares unsupported requirement "{requirementName}" for app "{appName}". The requirement will be ignored until a matching validator tagged with "app.requirements_validator" is registered.',
                [
                    'requirementName' => $requirementName,
                    'appName' => $manifest->getMetadata()->getName(),
                ]
            );
        }
    }
}
