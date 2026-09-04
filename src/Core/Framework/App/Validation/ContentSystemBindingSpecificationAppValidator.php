<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\ContentSystemBindingSpecificationSchemaError;
use Shopware\Core\Framework\App\Validation\Error\ErrorCollection;
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

        try {
            $this->loader->loadDtosFromTypeDirectory($typesDirectory, $source, $appName, $typeOverlay);
        } catch (ContentSystemException $e) {
            $violations[] = \sprintf('in "%s": %s', $typesDirectory, $e->getMessage());
        }

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
}
