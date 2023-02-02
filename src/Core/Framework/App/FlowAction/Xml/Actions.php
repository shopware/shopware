<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\FlowAction\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;

/**
 * @internal
 */
class Actions extends XmlElement
{
    /**
     * @var Action[]
     */
    protected array $actions;

    public function __construct(array $data)
    {
        $this->actions = $data;
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parseFlowActions($element));
    }

    /**
     * @return Action[]
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    private static function parseFlowActions(\DOMElement $element): array
    {
        $actions = [];
        foreach ($element->getElementsByTagName('flow-action') as $flowAction) {
            $actions[] = Action::fromXml($flowAction);
        }

        return $actions;
    }
}
