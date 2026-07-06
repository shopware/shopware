<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\ContentSystemBindingSpecificationSchemaError;
use Shopware\Core\Framework\App\Validation\Error\ErrorCollection;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\ResolvedBindingSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
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
    ) {
    }

    /**
     * Validates the schema of the inline `bindings:` sections of the app's element-type files. Every
     * canonicalization or type-consistency failure becomes a schema error, never an exception, so
     * `manifest:validate` reports what install would reject. Collisions within the source are the loader's job
     * (its per-directory dedup). All violations aggregate into ONE schema error, because ErrorCollection keys by
     * message key and a second error of the same class would replace the first.
     */
    public function validate(Manifest $manifest, Context $context): ErrorCollection
    {
        $errors = new ErrorCollection();

        $appName = $manifest->getMetadata()->getName();
        $source = 'app:' . $appName;
        $typesDirectory = $manifest->getPath() . '/' . self::TYPES_DIRECTORY;

        $typeOverlay = $this->buildTypeOverlay($typesDirectory, $source, $appName);

        $violations = [];
        $specifications = $this->collect($violations, $typesDirectory, fn () => $this->loader->loadDtosFromTypeDirectory($typesDirectory, $source, $appName, $typeOverlay));

        $this->detectPromotedConflicts($violations, $specifications);

        if ($violations !== []) {
            $errors->add(new ContentSystemBindingSpecificationSchemaError($violations));
        }

        return $errors;
    }

    /**
     * The app's own types keyed by resolved name, resolved before the registry when canonicalizing the bindings.
     * Malformed app types are the element-type validator's error to report; here they fall back to an empty overlay
     * so a binding on an app-own type surfaces as unknown-type rather than escaping this soft boundary as an
     * exception.
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
     * The promoted-uniqueness invariant at the manifest boundary: reject the app promoting one type twice across
     * its own specifications. Soft: every conflict is a schema error, never an exception.
     *
     * @param list<string> $violations
     * @param list<ResolvedBindingSpecificationDto> $specifications
     */
    private function detectPromotedConflicts(array &$violations, array $specifications): void
    {
        $appPromotedByType = [];

        foreach ($specifications as $resolvedDto) {
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
        }
    }
}
