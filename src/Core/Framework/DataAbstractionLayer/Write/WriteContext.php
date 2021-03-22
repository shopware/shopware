<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageDefinition;

class WriteContext
{
    private const SPACER = '::';

    /**
     * @var array
     */
    private $paths = [];

    /**
     * @var Context
     */
    private $context;

    /**
     * @var array
     */
    private $languages;

    /**
     * @var string[]|null
     */
    private $languageCodeIdMapping;

    /**
     * @var WriteException
     */
    private $exceptions;

    private function __construct(Context $context)
    {
        $this->context = $context;
        $this->exceptions = new WriteException();
    }

    public function setLanguages(array $languages): void
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
            return $this->getLanguages()[mb_strtolower($identifier)]['id'] ?? null;
        }
        $mapping = $this->getLanguageCodeToIdMapping();

        return $mapping[mb_strtolower($identifier)] ?? null;
    }

    public static function createFromContext(Context $context): self
    {
        $self = new self($context);
        $self->set(LanguageDefinition::class, 'id', $context->getLanguageId());

        return $self;
    }

    public function set(string $className, string $propertyName, string $value): void
    {
        $this->paths[$this->buildPathName($className, $propertyName)] = $value;
    }

    public function get(string $className, string $propertyName)
    {
        $path = $this->buildPathName($className, $propertyName);

        if (!$this->has($className, $propertyName)) {
            throw new \InvalidArgumentException(sprintf('Unable to load %s: %s', $path, print_r($this->paths, true)));
        }

        return $this->paths[$path];
    }

    public function has(string $className, string $propertyName): bool
    {
        $path = $this->buildPathName($className, $propertyName);

        return isset($this->paths[$path]);
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

    public function getExceptions(): WriteException
    {
        return $this->exceptions;
    }

    public function scope(string $scope, callable $callback): void
    {
        $originalContext = $this->context;

        $this->context->scope($scope, function (Context $context) use ($callback, $originalContext): void {
            $this->context = $context;
            $callback($this);
            $this->context = $originalContext;
        });
    }

    public function resetExceptions(): void
    {
        $this->exceptions = new WriteException();
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
            $mapping[mb_strtolower($language['code'])] = $language['id'];
        }

        return $this->languageCodeIdMapping = $mapping;
    }

    private function buildPathName(string $className, string $propertyName): string
    {
        return $className . self::SPACER . $propertyName;
    }
}
