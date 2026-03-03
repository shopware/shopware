<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation;

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
        if ($this->environment !== 'prod') {
            return [];
        }

        $validationErrors = [];
        foreach ($this->validators as $validator) {
            if (!$validator->required($manifest)) {
                continue;
            }

            if (!$validator->satisfied($manifest)) {
                $validationErrors[] = new UnmetRequirement(
                    $manifest->getMetadata()->getName(),
                    $validator::name(),
                    $validator->actionableResolution()
                );
            }
        }

        return $validationErrors;
    }
}
