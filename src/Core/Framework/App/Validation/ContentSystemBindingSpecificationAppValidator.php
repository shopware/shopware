<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation;

use Shopware\Core\Framework\App\Lifecycle\Persister\ContentSystemBindingSpecificationPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\ContentSystemBindingSpecificationSchemaError;
use Shopware\Core\Framework\App\Validation\Error\ErrorCollection;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\ResolvedBindingSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ContentSystemBindingSpecificationAppValidator extends AbstractManifestValidator
{
    private const TYPES_DIRECTORY = 'Resources/content-system/types';

    public function __construct(
        private readonly YamlBindingSpecificationLoader $loader,
        private readonly YamlTypeLoader $typeLoader,
        private readonly AbstractContentSystemBindingSpecificationRegistry $registry,
    ) {
    }

    /**
     * Validates the schema of the app's standalone binding files and the inline `bindings:` sections of its
     * element-type files. Every canonicalization or type-consistency failure becomes a schema error, never an
     * exception, so `manifest:validate` reports what install would reject. The two forms are validated
     * independently (a failure in one does not suppress the other), and a bare-id collision across the two forms
     * is reported here, matching the persister's install-time rejection. Collisions within one form are the
     * loader's job (its per-directory dedup). All violations aggregate into ONE schema error, because
     * ErrorCollection keys by message key and a second error of the same class would replace the first.
     *
     * It also rejects an app specification promoting a type that the aggregated registry already promotes, and two
     * of the app's own specifications promoting one type. This registry-backed check is inherently incomplete:
     * the registry sees only ACTIVE apps' rows (the DB loader filters `active = 1`), and apps install inactive by
     * default, so install-then-activate ordering can hide a conflict from it; the aggregation backstop in
     * {@see AbstractContentSystemBindingSpecificationRegistry::all()} covers what slips through.
     */
    public function validate(Manifest $manifest, Context $context): ErrorCollection
    {
        $errors = new ErrorCollection();

        $appName = $manifest->getMetadata()->getName();
        $source = 'app:' . $appName;
        $standaloneDirectory = $manifest->getPath() . '/' . ContentSystemBindingSpecificationPersister::DIRECTORY;
        $typesDirectory = $manifest->getPath() . '/' . self::TYPES_DIRECTORY;

        $typeOverlay = $this->buildTypeOverlay($typesDirectory, $source, $appName);

        $violations = [];
        $standalone = $this->collect($violations, $standaloneDirectory, fn () => $this->loader->loadDtosFromDirectory($standaloneDirectory, $source, $typeOverlay));
        $inline = $this->collect($violations, $typesDirectory, fn () => $this->loader->loadInlineDtosFromTypeDirectory($typesDirectory, $source, $appName, $typeOverlay));

        $this->detectCrossFormCollisions($violations, $standalone, $inline);
        $this->detectPromotedConflicts($violations, $standalone, $inline);

        if ($violations !== []) {
            $errors->add(new ContentSystemBindingSpecificationSchemaError($violations));
        }

        return $errors;
    }

    /**
     * The app's own types keyed by resolved name, resolved before the registry when canonicalizing the bindings.
     * Malformed app types are the element-type validator's error to report; here they fall back to an empty overlay
     * so a standalone binding on a core type still validates and an app-own-type binding surfaces as unknown-type
     * rather than escaping this soft boundary as an exception.
     *
     * @return array<string, ContentSystemElementTypeSpecification>
     */
    private function buildTypeOverlay(string $typesDirectory, string $source, string $prefix): array
    {
        try {
            return $this->typeLoader->loadOverlayFromDirectory($typesDirectory, $source, $prefix);
        } catch (ContentSystemException) {
            return [];
        }
    }

    /**
     * @param list<string> $violations
     * @param callable(): list<ResolvedBindingSpecificationDto> $load
     *
     * @return list<ResolvedBindingSpecificationDto>
     */
    private function collect(array &$violations, string $directory, callable $load): array
    {
        try {
            return $load();
        } catch (ContentSystemException $e) {
            $violations[] = \sprintf('in "%s": %s', $directory, $e->getMessage());

            return [];
        }
    }

    /**
     * @param list<string> $violations
     * @param list<ResolvedBindingSpecificationDto> $standalone
     * @param list<ResolvedBindingSpecificationDto> $inline
     */
    private function detectCrossFormCollisions(array &$violations, array $standalone, array $inline): void
    {
        $standaloneIds = [];
        foreach ($standalone as $resolvedDto) {
            $standaloneIds[$resolvedDto->id] = true;
        }

        foreach ($inline as $resolvedDto) {
            if (!isset($standaloneIds[$resolvedDto->id])) {
                continue;
            }

            $violations[] = \sprintf('inline binding "%s" collides with a standalone binding of the same id; binding ids are unique per app across both authoring forms', $resolvedDto->id);
        }
    }

    /**
     * The promoted-uniqueness invariant at the manifest boundary: reject the app promoting a type the
     * aggregated registry already promotes, and the app promoting one type twice across its own two forms. Soft:
     * every conflict is a schema error, never an exception. The registry read is inherently best-effort (only
     * active apps' rows are visible) and its throw path is the caller's, not wrapped here.
     *
     * @param list<string> $violations
     * @param list<ResolvedBindingSpecificationDto> $standalone
     * @param list<ResolvedBindingSpecificationDto> $inline
     */
    private function detectPromotedConflicts(array &$violations, array $standalone, array $inline): void
    {
        $appPromotedByType = [];

        foreach ([$standalone, $inline] as $resolvedDtos) {
            foreach ($resolvedDtos as $resolvedDto) {
                $specification = $resolvedDto->toSpecification();

                if (!$specification->isPromoted()) {
                    continue;
                }

                $type = $specification->type();

                if (isset($appPromotedByType[$type])) {
                    $violations[] = \sprintf('binding "%s" promotes type "%s", which this app already promotes via binding "%s"; at most one specification may be promoted per type', $resolvedDto->id, $type, $appPromotedByType[$type]);

                    continue;
                }

                $appPromotedByType[$type] = $resolvedDto->id;

                $registered = $this->registeredPromotedId($type);
                if ($registered !== null) {
                    $violations[] = \sprintf('binding "%s" promotes type "%s", which is already promoted by the registered specification "%s"; at most one specification may be promoted per type', $resolvedDto->id, $type, $registered);
                }
            }
        }
    }

    /**
     * The source-qualified id of a registered promoted specification for the type, or null when none is promoted.
     */
    private function registeredPromotedId(string $type): ?string
    {
        foreach ($this->registry->byType($type) as $specification) {
            if ($specification->isPromoted()) {
                return $specification->qualifiedId();
            }
        }

        return null;
    }
}
