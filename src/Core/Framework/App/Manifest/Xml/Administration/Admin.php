<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Administration;

use Shopware\Core\Framework\App\Exception\InvalidArgumentException;
use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class Admin extends XmlElement
{
    /**
     * @var list<ActionButton>
     */
    protected array $actionButtons = [];

    /**
     * @var list<Module>
     */
    protected array $modules = [];

    protected ?MainModule $mainModule = null;

    protected ?string $baseAppUrl = null;

    /**
     * @return list<ActionButton>
     */
    public function getActionButtons(): array
    {
        return $this->actionButtons;
    }

    /**
     * @return list<Module>
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    public function getMainModule(): ?MainModule
    {
        return $this->mainModule;
    }

    public function getBaseAppUrl(): ?string
    {
        return $this->baseAppUrl;
    }

    /**
     * @return array<string>
     */
    public function getUrls(): array
    {
        $urls = [];

        if ($this->baseAppUrl) {
            $urls[] = $this->baseAppUrl;
        }

        if ($this->mainModule) {
            $urls[] = $this->mainModule->getSource();
        }

        foreach ($this->modules as $module) {
            $urls[] = $module->getSource();
        }

        foreach ($this->actionButtons as $actionButton) {
            $urls[] = $actionButton->getUrl();
        }

        return array_filter($urls);
    }

    protected static function parse(\DOMElement $element): array
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

        $baseAppUrl = null;
        foreach ($element->getElementsByTagName('base-app-url') as $baseUrl) {
            // main-module element has to be unique due to schema restrictions
            $baseAppUrl = $baseUrl->nodeValue;
        }

        return [
            'actionButtons' => $actionButtons,
            'modules' => $modules,
            'mainModule' => $mainModule,
            'baseAppUrl' => $baseAppUrl,
        ];
    }
}
