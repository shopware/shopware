<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Field;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class FieldFactory
{
    private const MAPPING = [
        'int' => IntField::class,
        'bool' => BoolField::class,
        'float' => FloatField::class,
        'string' => StringField::class,
        'text' => TextField::class,
        'email' => EmailField::class,
        'json' => JsonField::class,
        'date' => DateField::class,
        'price' => PriceField::class,
        'many-to-many' => ManyToManyField::class,
        'many-to-one' => ManyToOneField::class,
        'one-to-many' => OneToManyField::class,
        'one-to-one' => OneToOneField::class,
    ];

    /**
     * @internal
     */
    public static function createFromXml(\DOMElement $element): Field
    {
        /** @var class-string<Field>|null $class */
        $class = self::MAPPING[$element->tagName] ?? null;

        if (!$class) {
            throw new \RuntimeException(sprintf('Field type "%s" not found', $element->tagName));
        }

        return $class::fromXml($element);
    }
}
