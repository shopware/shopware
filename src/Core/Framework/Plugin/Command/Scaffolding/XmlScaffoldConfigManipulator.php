<?php

namespace Shopware\Core\Framework\Plugin\Command\Scaffolding;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class XmlScaffoldConfigManipulator
{
    public const CONFIG_TYPE_ROUTE = 'route';
    public const CONFIG_TYPE_SERVICE = 'service';
    public const CONFIG_TYPE_CONFIG = 'config';

    public function addConfig(
        string $xmlConfigPath,
        string $namespace,
        string $xmlEntry
    ): string
    {
        $configType = $this->resolveConfigType($xmlConfigPath);
        $rootNodeName = $this->resolveRootNodeName($xmlConfigPath);

        $xmlEncoder = new XmlEncoder([XmlEncoder::ROOT_NODE_NAME => $rootNodeName]);
        $content = str_replace(
            '{{ namespace }}',
            $namespace,
            $xmlEntry
        );

        $nodeXml = $xmlEncoder->decode($content, 'xml');
        $xml = $xmlEncoder->decode(@file_get_contents($xmlConfigPath), '');

        if($configType === self::CONFIG_TYPE_ROUTE) {
            // in case of file is empty (only root node)
            // ensure import node is array
            if(isset($xml['#']) && \is_string($xml['#']) && trim($xml['#']) === "") {
                $xml['import'] = [];
            }

            // if only one route is defined, services plain array of that route
            // routes => ['@resource' => ..., ... ] so I need to change it in array of routes
            if(isset($xml['import']) && array_key_exists('@resource', $xml['import'])) {
                $xml['import'] = [$xml['import']];
            }

            if(isset($xml['import']) && array_key_exists('@resource', $xml['import'])) {
                $xml['import'] = [$xml['import']];
            }

            $imports = (array) ($xml['#']['import'] ?? $xml['import']);
            $imports[] = $nodeXml;
            $xml['import'] = array_merge(...$imports);
        }

        if($configType === self::CONFIG_TYPE_SERVICE) {
            // Ensure services is array
            if(\is_string($xml['services']) && trim($xml['services']) === "") {
                $xml['services'] = [];
            }

            // Ensure services/service is defined
            if(!isset($xml['services']['service'])) {
                $xml['services']['service'] = [];
            }

            // if only one service is defined, services plain array of that service
            // services => ['@id' => ..., ... ] so I need to change it in array of services
            if(array_key_exists('@id', $xml['services']['service'])) {
                $xml['services']['service'] = [$xml['services']['service']];
            }

            $services = (array) $xml['services']['service'];
            $services[] = $nodeXml;
            $xml['services']['service'] = $services;
        }

        if($configType === self::CONFIG_TYPE_CONFIG) {
            // in case of file is empty (only root node)
            // ensure card is array
            if(isset($xml['#']) && \is_string($xml['#']) && trim($xml['#']) === "") {
                $xml['card'] = [];
            }

            if(isset($xml['card']) && array_key_exists('title', $xml['card'])) {
                $xml['card'] = [$xml['card']];
            }

            $cards = (array) ($xml['#']['card'] ?? $xml['card']);
            $cards[] = $nodeXml;
            $xml['card'] = $cards;
        }

        return $xmlEncoder->encode($xml, 'xml', [XmlEncoder::FORMAT_OUTPUT => true]);
    }

    private function resolveConfigType(string $getPath): string
    {
        $filename = pathinfo($getPath, PATHINFO_FILENAME);

        if($filename === 'services') return self::CONFIG_TYPE_SERVICE;
        if($filename === 'routes') return self::CONFIG_TYPE_ROUTE;
        if($filename === 'config') return self::CONFIG_TYPE_CONFIG;

        throw new \RuntimeException('Unknown config type');
    }

    private function resolveRootNodeName(string $getPath): string
    {
        $configType = pathinfo($getPath, PATHINFO_FILENAME);

        if($configType === 'services') return 'container';
        if($configType === 'routes') return 'routes';
        if($configType === 'config') return 'config';

        throw new \RuntimeException('Unknown root name');
    }
}
