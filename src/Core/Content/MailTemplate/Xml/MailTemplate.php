<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('after-sales')]
class MailTemplate extends XmlElement
{
    protected const REQUIRED_FIELDS = [
        'technicalName',
        'name',
        'subject',
    ];

    private const TRANSLATABLE_FIELDS = ['name', 'subject', 'sender-name', 'description'];

    protected string $technicalName;

    /**
     * @var array<string, string>
     */
    protected array $name;

    /**
     * @var array<string, string>
     */
    protected array $subject;

    /**
     * @var array<string, string>
     */
    protected array $senderName = [];

    /**
     * @var array<string, string>
     */
    protected array $description = [];

    /**
     * @var array<string, string>
     */
    protected array $availableEntities = [];

    /**
     * @var array<string, string>
     */
    protected array $contentHtml = [];

    /**
     * @var array<string, string>
     */
    protected array $contentPlain = [];

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    /**
     * @return array<string, string>
     */
    public function getName(): array
    {
        return $this->name;
    }

    /**
     * @return array<string, string>
     */
    public function getSubject(): array
    {
        return $this->subject;
    }

    /**
     * @return array<string, string>
     */
    public function getSenderName(): array
    {
        return $this->senderName;
    }

    /**
     * @return array<string, string>
     */
    public function getDescription(): array
    {
        return $this->description;
    }

    /**
     * @return array<string, string>
     */
    public function getAvailableEntities(): array
    {
        return $this->availableEntities;
    }

    /**
     * @return array<string, string>
     */
    public function getContentHtml(): array
    {
        return $this->contentHtml;
    }

    /**
     * @return array<string, string>
     */
    public function getContentPlain(): array
    {
        return $this->contentPlain;
    }

    /**
     * @param array<string, string> $contentHtml
     */
    public function setContentHtml(array $contentHtml): void
    {
        $this->contentHtml = $contentHtml;
    }

    /**
     * @param array<string, string> $contentPlain
     */
    public function setContentPlain(array $contentPlain): void
    {
        $this->contentPlain = $contentPlain;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = XmlParserUtils::parseAttributes($element);

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $values = self::parseChild($child, $values);
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private static function parseChild(\DOMElement $child, array $values): array
    {
        if (\in_array($child->tagName, self::TRANSLATABLE_FIELDS, true)) {
            return XmlParserUtils::mapTranslatedTag($child, $values);
        }

        if ($child->tagName === 'available-entities') {
            $entities = [];
            foreach ($child->childNodes as $entity) {
                if (!$entity instanceof \DOMElement) {
                    continue;
                }
                $entities[$entity->tagName] = trim($entity->nodeValue ?? '');
            }
            $values['availableEntities'] = $entities;

            return $values;
        }

        $values[XmlParserUtils::kebabCaseToCamelCase($child->tagName)] = $child->nodeValue;

        return $values;
    }
}
