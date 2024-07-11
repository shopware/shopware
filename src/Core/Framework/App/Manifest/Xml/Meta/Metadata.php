<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Meta;

use Composer\Package\Version\VersionParser;
use Composer\Semver\Constraint\ConstraintInterface;
use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\App\Validation\Error\MissingTranslationError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class Metadata extends XmlElement
{
    protected const REQUIRED_FIELDS = [
        'label',
        'name',
        'author',
        'copyright',
        'license',
        'version',
    ];

    private const TRANSLATABLE_FIELDS = [
        'label',
        'description',
        'privacyPolicyExtensions',
    ];

    /**
     * @var array<string, string>
     */
    protected array $label = [];

    /**
     * @var array<string, string>
     */
    protected array $description = [];

    protected string $name;

    protected bool $selfManaged = false;

    protected string $author;

    protected string $copyright;

    protected ?string $license;

    protected ?string $compatibility;

    protected string $version;

    protected ?string $icon = null;

    protected ?string $privacy = null;

    /**
     * @var array<string, string>
     */
    protected array $privacyPolicyExtensions = [];

    protected ?string $url = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $defaultLocale): array
    {
        $data = parent::toArray($defaultLocale);

        foreach (self::TRANSLATABLE_FIELDS as $TRANSLATABLE_FIELD) {
            $translatableField = self::kebabCaseToCamelCase($TRANSLATABLE_FIELD);

            $data[$translatableField] = $this->ensureTranslationForDefaultLanguageExist(
                $data[$translatableField],
                $defaultLocale
            );
        }

        return $data;
    }

    public function validateTranslations(): ?MissingTranslationError
    {
        $missingTranslations = [];
        // used locales are valid, see Manifest::createFromXmlFile()
        $usedLocales = array_keys(array_merge($this->getDescription(), $this->getPrivacyPolicyExtensions()));

        // label is required in app_translation and must therefore be available in all languages
        $diff = array_diff($usedLocales, array_keys($this->getLabel()));

        if (empty($diff)) {
            return null;
        }

        $missingTranslations['label'] = $diff;

        return new MissingTranslationError(self::class, $missingTranslations);
    }

    /**
     * @return array<string, string>
     */
    public function getLabel(): array
    {
        return $this->label;
    }

    /**
     * @return array<string, string>
     */
    public function getDescription(): array
    {
        return $this->description;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isSelfManaged(): bool
    {
        return $this->selfManaged;
    }

    public function setSelfManaged(bool $selfManaged): void
    {
        $this->selfManaged = $selfManaged;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getCopyright(): string
    {
        return $this->copyright;
    }

    public function getLicense(): ?string
    {
        return $this->license;
    }

    public function getCompatibility(): ConstraintInterface
    {
        $constraint = $this->compatibility ?? '>=6.4.0';

        return (new VersionParser())->parseConstraints($constraint);
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getPrivacy(): ?string
    {
        return $this->privacy;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * @return array<string, string>
     */
    public function getPrivacyPolicyExtensions(): array
    {
        return $this->privacyPolicyExtensions;
    }

    protected static function parse(\DOMElement $element): array
    {
        /**
         * @var array{
         *      label: array<string, string>,
         *      description: array<string, string>,
         *      name: string,
         *      type: string,
         *      author: string,
         *      copyright: string,
         *      license: ?string,
         *      compatibility: ?string,
         *      version: ?string,
         *      icon: ?string,
         *      privacy: ?string,
         *      privacyPolicyExtensions: array<string, string>,
         *      url: ?string,
         *  } $values
         */
        $values = XmlParserUtils::parseChildrenAndTranslate($element, self::TRANSLATABLE_FIELDS);

        return $values;
    }
}
