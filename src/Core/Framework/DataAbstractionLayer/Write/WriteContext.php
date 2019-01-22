<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\Language\LanguageDefinition;

class WriteContext
{
    private const SPACER = '::';

    /**
     * @var array
     */
    public $paths = [];

    /**
     * @var Context
     */
    private $context;

    /**
     * @var array[]
     *
     * @example
     * [
     *      product
     *          uuid-1 => null
     *          uuid-2 => uuid-1
     * ]
     */
    private $inheritance = [];

    /**
     * @var array
     */
    private $languages;

    /**
     * @var string[]|null
     */
    private $languageCodeIdMapping;

    private function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function addInheritance(string $definition, array $inheritance): void
    {
        if (!isset($this->inheritance[$definition])) {
            $this->inheritance[$definition] = [];
        }

        $this->inheritance[$definition] = array_replace_recursive(
            $this->inheritance[$definition],
            $inheritance
        );
    }

    public function setLanguages($languages): void
    {
        $this->languages = $languages;
        $this->languageCodeIdMapping = null;
    }

    public function getLanguages(): array
    {
        if (empty($this->languages)) {
            throw new \RuntimeException('languages not initialized');
        }

        return $this->languages;
    }

    public function getLanguageId(string $identifier): ?string
    {
        if (Uuid::isValid($identifier)) {
            return $this->getLanguages()[strtolower($identifier)]['id'] ?? null;
        }
        $mapping = $this->getLanguageCodeToIdMapping();

        return $mapping[strtolower($identifier)] ?? null;
    }

    public static function createFromContext(Context $context): self
    {
        $self = new self($context);
        $self->set(LanguageDefinition::class, 'id', $context->getLanguageId());

        return $self;
    }

    /**
     * @param string $className
     * @param string $propertyName
     * @param string $value
     */
    public function set(string $className, string $propertyName, string $value): void
    {
        $this->paths[$this->buildPathName($className, $propertyName)] = $value;
    }

    /**
     * @param string $className
     * @param string $propertyName
     *
     * @return mixed
     */
    public function get(string $className, string $propertyName)
    {
        $path = $this->buildPathName($className, $propertyName);

        if (!$this->has($className, $propertyName)) {
            throw new \InvalidArgumentException(sprintf('Unable to load %s: %s', $path, print_r($this->paths, true)));
        }

        return $this->paths[$path];
    }

    /**
     * @param string $className
     * @param string $propertyName
     *
     * @return bool
     */
    public function has(string $className, string $propertyName): bool
    {
        $path = $this->buildPathName($className, $propertyName);

        return isset($this->paths[$path]);
    }

    /**
     * @param EntityDefinition|string $definition
     * @param array                   $raw
     *
     * @return bool
     */
    public function isChild(string $definition, array $raw): bool
    {
        if (array_key_exists('parent', $raw)) {
            return true;
        }

        /** @var ManyToOneAssociationField $parent */
        $parent = $definition::getFields()->get('parent');

        $fk = $definition::getFields()->getByStorageName(
            $parent->getStorageName()
        );

        if (isset($raw[$fk->getPropertyName()])) {
            return true;
        }

        if (!array_key_exists($definition, $this->inheritance)) {
            return false;
        }

        $inheritance = $this->inheritance[$definition];

        return isset($inheritance[$raw['id']]);
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function resetPaths(): void
    {
        $this->paths = [];
        $this->set(LanguageDefinition::class, 'id', $this->context->getLanguageId());
    }

    public function createWithVersionId(string $versionId): self
    {
        return self::createFromContext($this->getContext()->createWithVersionId($versionId));
    }

    private function getLanguageCodeToIdMapping(): array
    {
        if ($this->languageCodeIdMapping !== null) {
            return $this->languageCodeIdMapping;
        }

        $mapping = [];
        $languages = $this->getLanguages();
        foreach ($languages as $language) {
            if (!$language['code']) {
                continue;
            }
            $mapping[strtolower($language['code'])] = $language['id'];
        }

        return $this->languageCodeIdMapping = $mapping;
    }

    /**
     * @param string $className
     * @param string $propertyName
     *
     * @return string
     */
    private function buildPathName(string $className, string $propertyName): string
    {
        return $className . self::SPACER . $propertyName;
    }
}
