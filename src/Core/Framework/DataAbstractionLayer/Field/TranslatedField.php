<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\System\Language\LanguageDefinition;

class TranslatedField extends Field
{
    public const PRIORITY = 100;
    /**
     * @var string
     */
    private $foreignClassName;

    /**
     * @var string
     */
    private $foreignFieldName;

    public function __construct(string $propertyName)
    {
        $this->foreignClassName = LanguageDefinition::class;
        $this->foreignFieldName = 'id';

        parent::__construct($propertyName);
    }

    public function getExtractPriority(): int
    {
        return self::PRIORITY;
    }

    public function getForeignClassName(): string
    {
        return $this->foreignClassName;
    }

    public function getForeignFieldName(): string
    {
        return $this->foreignFieldName;
    }
}
