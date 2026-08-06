<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Consent;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class Consent extends XmlElement
{
    protected const REQUIRED_FIELDS = [
        'name',
        'scope',
    ];

    protected string $name;

    protected string $scope;

    protected ?string $revision = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function getRevision(): ?string
    {
        return $this->revision;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function parse(\DOMElement $element): array
    {
        return XmlParserUtils::parseChildren($element);
    }
}
