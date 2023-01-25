<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseHelper;

use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

/**
 * @internal
 */
class ExtensionHelper
{
    final public const IGNORED_PROPERTIES = ['extension', 'extensions', 'elements'];

    /**
     * @var PropertyInfoExtractor
     */
    protected $propertyInfoExtractor;

    /**
     * @var PropertyAccessor
     */
    protected $propertyAccessor;

    public function __construct()
    {
        $reflectionExtractor = new ReflectionExtractor();
        $phpDocExtractor = new PhpDocExtractor();
        $this->propertyInfoExtractor = new PropertyInfoExtractor(
            [$reflectionExtractor],
            [$phpDocExtractor, $reflectionExtractor],
            [$phpDocExtractor],
            [$reflectionExtractor]
        );
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * Removes all extensions from an object (recursive)
     * Only works if the properties are public or accessible by getter
     */
    public function removeExtensions($object): void
    {
        if (\is_scalar($object)) {
            return;
        }

        if ($object instanceof Collection) {
            $object->map(function ($element): void {
                $this->removeExtensions($element);
            });
        }

        if ($object instanceof Struct) {
            $properties = $this->propertyInfoExtractor->getProperties($object::class);

            foreach ($properties as $property) {
                if (\in_array($property, self::IGNORED_PROPERTIES, true)) {
                    continue;
                }

                try {
                    $this->removeExtensions($this->propertyAccessor->getValue($object, $property));
                } catch (\ArgumentCountError) {
                    // nth
                }
            }

            $object->setExtensions([]);
        }
    }
}
