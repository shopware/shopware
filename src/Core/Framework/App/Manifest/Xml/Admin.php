<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

class Admin extends XmlElement
{
    /**
     * @var ActionButton[]
     */
    protected $actionButtons = [];

    /**
     * @var Module[]
     */
    protected $modules = [];

    private function __construct(array $data)
    {
        foreach ($data as $property => $value) {
            $this->$property = $value;
        }
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parseChilds($element));
    }

    /**
     * @return ActionButton[]
     */
    public function getActionButtons(): array
    {
        return $this->actionButtons;
    }

    /**
     * @return Module[]
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    private static function parseChilds(\DOMElement $element): array
    {
        $actionButtons = [];
        foreach ($element->getElementsByTagName('action-button') as $actionButton) {
            $actionButtons[] = ActionButton::fromXml($actionButton);
        }

        $modules = [];
        foreach ($element->getElementsByTagName('module') as $module) {
            $modules[] = Module::fromXml($module);
        }

        return [
            'actionButtons' => $actionButtons,
            'modules' => $modules,
        ];
    }
}
