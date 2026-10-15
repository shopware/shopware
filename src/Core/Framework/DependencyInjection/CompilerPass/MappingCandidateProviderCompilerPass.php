<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\ContentSystem\Mapping\Provider\AbstractMappingCandidateProvider;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Checks stable mapping candidate declarations while the service container is built.
 *
 * @internal
 */
#[Package('framework')]
final class MappingCandidateProviderCompilerPass implements CompilerPassInterface
{
    private const TAG = 'content_system.mapping_candidate_provider';

    public function process(ContainerBuilder $container): void
    {
        /** @var array<string, array<string, string>> $providersByRootAndKey */
        $providersByRootAndKey = [];
        /** @var array<string, string> $wildcardProvidersByKey */
        $wildcardProvidersByKey = [];

        foreach ($container->findTaggedServiceIds(self::TAG) as $serviceId => $_tags) {
            $class = $container->getDefinition($serviceId)->getClass();
            if ($class === null || !is_subclass_of($class, AbstractMappingCandidateProvider::class)) {
                throw DependencyInjectionException::taggedServiceHasWrongType($serviceId, self::TAG, AbstractMappingCandidateProvider::class);
            }

            foreach ($class::registeredCandidateKeys() as $rootSource => $candidateKeys) {
                foreach ($candidateKeys as $candidateKey) {
                    if ($rootSource === '*') {
                        if (isset($wildcardProvidersByKey[$candidateKey])) {
                            throw DependencyInjectionException::mappingCandidateProviderKeyCollision(
                                '*',
                                $candidateKey,
                                $wildcardProvidersByKey[$candidateKey],
                                $serviceId,
                            );
                        }

                        $wildcardProvidersByKey[$candidateKey] = $serviceId;
                        continue;
                    }

                    if (isset($providersByRootAndKey[$rootSource][$candidateKey])) {
                        throw DependencyInjectionException::mappingCandidateProviderKeyCollision(
                            $rootSource,
                            $candidateKey,
                            $providersByRootAndKey[$rootSource][$candidateKey],
                            $serviceId,
                        );
                    }

                    $providersByRootAndKey[$rootSource][$candidateKey] = $serviceId;
                }
            }
        }

        foreach ($providersByRootAndKey as $rootSource => $providersByKey) {
            foreach ($providersByKey as $candidateKey => $serviceId) {
                if (!isset($wildcardProvidersByKey[$candidateKey])) {
                    continue;
                }

                throw DependencyInjectionException::mappingCandidateProviderKeyCollision(
                    $rootSource,
                    $candidateKey,
                    $wildcardProvidersByKey[$candidateKey],
                    $serviceId,
                );
            }
        }
    }
}
