<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Symfony\Component\Config\Util\XmlUtils;

class XmlPluginInfoReader
{
    public function read($file)
    {
        try {
            $dom = XmlUtils::loadFile($file, __DIR__ . '/Schema/plugin.xsd');
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf('Unable to parse file "%s". Message: %s', $file, $e->getMessage()), $e->getCode(), $e);
        }

        return $this->parseInfo($dom);
    }

    /**
     * @param \DOMDocument $xml
     *
     * @return array
     */
    private function parseInfo(\DOMDocument $xml)
    {
        $xpath = new \DOMXPath($xml);

        $entries = $xpath->query('//plugin');

        if ($entries === false) {
            // TODO: throw Exception?
            return [];
        }

        $entry = $entries[0];
        $info = [];

        foreach ($this->getChildren($entry, 'label') as $label) {
            $lang = $label->getAttribute('lang') ? $label->getAttribute('lang') : 'en';
            $info['label'][$lang] = $label->nodeValue;
        }

        foreach ($this->getChildren($entry, 'description') as $description) {
            $lang = $description->getAttribute('lang') ? $description->getAttribute('lang') : 'en';
            $info['description'][$lang] = trim($description->nodeValue);
        }

        $simpleKeys = ['version', 'license', 'author', 'copyright', 'link'];
        foreach ($simpleKeys as $simpleKey) {
            if ($names = $this->getChildren($entry, $simpleKey)) {
                $info[$simpleKey] = $names[0]->nodeValue;
            }
        }

        foreach ($this->getChildren($entry, 'changelog') as $changelog) {
            $version = $changelog->getAttribute('version');

            foreach ($this->getChildren($changelog, 'changes') as $changes) {
                $lang = $changes->getAttribute('lang') ? $changes->getAttribute('lang') : 'en';
                $info['changelog'][$version][$lang][] = $changes->nodeValue;
            }
        }

        $compatibility = $this->getFirstChild($entry, 'compatibility');
        if ($compatibility) {
            $info['compatibility'] = [
                'minVersion' => $compatibility->getAttribute('minVersion'),
                'maxVersion' => $compatibility->getAttribute('maxVersion'),
                'blacklist' => $this->getChildrenValues($compatibility, 'blacklist'),
            ];
        }

        $requiredPlugins = $this->getFirstChild($entry, 'requiredPlugins');
        if ($requiredPlugins) {
            $info['requiredPlugins'] = $this->parseRequiredPlugins($requiredPlugins);
        }

        return $info;
    }

    /**
     * Get child elements by name.
     *
     * @return \DOMElement[]
     */
    private function getChildren(\DOMNode $node, string $name): array
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $name) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * @param \DOMNode $node
     * @param mixed    $name
     *
     * @return null|\DOMElement
     */
    private function getFirstChild(\DOMNode $node, $name)
    {
        if ($children = $this->getChildren($node, $name)) {
            return $children[0];
        }

        return null;
    }

    /**
     * Get child element values by name.
     *
     * @return string[]
     */
    private function getChildrenValues(\DOMNode $node, string $name): array
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $name) {
                $children[] = $child->nodeValue;
            }
        }

        return $children;
    }

    /**
     * @param \DOMNode $requiredPlugins
     *
     * @return array
     */
    private function parseRequiredPlugins(\DOMNode $requiredPlugins)
    {
        $resolvedPlugins = $this->getChildren($requiredPlugins, 'requiredPlugin');
        $plugins = [];
        foreach ($resolvedPlugins as $requiredPlugin) {
            $plugins[] = [
                'pluginName' => $requiredPlugin->getAttribute('pluginName'),
                'minVersion' => $requiredPlugin->getAttribute('minVersion'),
                'maxVersion' => $requiredPlugin->getAttribute('maxVersion'),
                'blacklist' => $this->getChildrenValues($requiredPlugin, 'blacklist'),
            ];
        }

        return $plugins;
    }
}
