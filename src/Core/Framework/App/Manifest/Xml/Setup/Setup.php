<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Setup;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class Setup extends XmlElement
{
    protected string $registrationUrl;

    protected ?string $secret = null;

    public function getRegistrationUrl(): string
    {
        return $this->registrationUrl;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    protected static function parse(\DOMElement $element): array
    {
        /** @var array{registrationUrl: string, secret: ?string} $values */
        $values = XmlParserUtils::parseChildren($element);

        return $values;
    }
}
