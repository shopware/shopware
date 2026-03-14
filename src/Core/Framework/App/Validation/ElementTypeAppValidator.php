<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\ElementTypeSchemaError;
use Shopware\Core\Framework\App\Validation\Error\ErrorCollection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ElementTypeAppValidator extends AbstractManifestValidator
{
    public function __construct(
        private readonly ElementTypeSpecificationSerializer $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function validate(Manifest $manifest, Context $context): ErrorCollection
    {
        $errors = new ErrorCollection();

        $typesDir = $manifest->getPath() . '/Resources/content-system/types';

        if (!is_dir($typesDir)) {
            return $errors;
        }

        try {
            $loader = new YamlTypeLoader($this->serializer, $this->validator, $typesDir);
            $loader->load();
        } catch (ContentSystemException $e) {
            $errors->add(new ElementTypeSchemaError(
                $typesDir,
                $e->getMessage()
            ));
        }

        return $errors;
    }
}
