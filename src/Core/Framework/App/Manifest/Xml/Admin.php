<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

use Shopware\Core\Framework\App\Exception\InvalidArgumentException;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class Admin extends XmlElement
{
    /**
     * @var ActionButton[]
     */
    protected array $actionButtons = [];

    /**
     * @var Module[]
     */
    protected array $modules = [];

    protected ?MainModule $mainModule;

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

    public function getMainModule(): ?MainModule
    {
        return $this->mainModule;
    }

    private static function parseChilds(\DOMElement $element): array
    {
        if (\count($element->getElementsByTagName('main-module')) > 1) {
            throw new InvalidArgumentException('Main module must only appear once');
        }

        $actionButtons = [];
        foreach ($element->getElementsByTagName('action-button') as $actionButton) {
            $actionButtons[] = ActionButton::fromXml($actionButton);
        }

        $modules = [];
        foreach ($element->getElementsByTagName('module') as $module) {
            $modules[] = Module::fromXml($module);
        }

        $mainModule = null;
        foreach ($element->getElementsByTagName('main-module') as $mainModuleNode) {
            // main-module element has to be unique due to schema restrictions
            $mainModule = MainModule::fromXml($mainModuleNode);
        }

        return [
            'actionButtons' => $actionButtons,
            'modules' => $modules,
            'mainModule' => $mainModule,
        ];
    }
}
