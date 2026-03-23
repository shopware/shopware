<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('after-sales')]
class MailTemplates extends XmlElement
{
    /**
     * @var list<MailTemplate>
     */
    protected array $mailTemplates = [];

    /**
     * @return list<MailTemplate>
     */
    public function getMailTemplates(): array
    {
        return $this->mailTemplates;
    }

    protected static function parse(\DOMElement $element): array
    {
        $mailTemplates = [];
        foreach ($element->getElementsByTagName('mail-template') as $mailTemplate) {
            $mailTemplates[] = MailTemplate::fromXml($mailTemplate);
        }

        return ['mailTemplates' => $mailTemplates];
    }
}
