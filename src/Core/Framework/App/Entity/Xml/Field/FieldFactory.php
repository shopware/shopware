<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Entity\Xml\Field;

use Shopware\Core\Framework\App\Exception\FieldTypeNotFoundException;

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
        'many-to-many' => ManyToManyField::class,
        'many-to-one' => ManyToOneField::class,
        'one-to-many' => OneToManyField::class,
        'one-to-one' => OneToOneField::class,
    ];

    public static function createFromXml(\DOMElement $element)
    {
        $class = self::MAPPING[$element->tagName] ?? null;

        if (!$class) {
            throw new FieldTypeNotFoundException($element->tagName);
        }

        return $class::fromXml($element);
    }
}
