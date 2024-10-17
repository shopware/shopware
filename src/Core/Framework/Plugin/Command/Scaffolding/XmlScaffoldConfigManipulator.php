<?php

namespace Shopware\Core\Framework\Plugin\Command\Scaffolding;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class XmlScaffoldConfigManipulator
{
    public const CONFIG_TYPE_ROUTE = 'route';
    public const CONFIG_TYPE_SERVICE = 'service';

    public function __construct(
        private readonly Filesystem $filesystem
    )
    {
    }

    public function addConfig(
        string $configType,
        string $xmlConfigPath,
        string $namespace,
        string $xmlEntry,
        string $rootNodeName
    ): string
    {
        if($this->filesystem->exists($xmlConfigPath)) {
            $xmlEncoder = new XmlEncoder([XmlEncoder::ROOT_NODE_NAME => $rootNodeName]);
            $content = str_replace(
                '{{ namespace }}',
                $namespace,
                $xmlEntry
            );

            $nodeXml = $xmlEncoder->decode($content, 'xml');
            $xml = $xmlEncoder->decode(@file_get_contents($xmlConfigPath), '');

            if($configType === self::CONFIG_TYPE_ROUTE) {
                // Ensure services is array
                if(\is_string($xml['#']) && trim($xml['#']) === "") {
                    $xml['#'] = ['import' => []];
                }

                // if only one service is defined, services plain array of that service
                // services => ['@id' => ..., ... ] so I need to change it in array of services
                if(array_key_exists('@resource', $xml['#'])) {
                    $xml['#'] = [$xml['#']];
                }

                $imports = (array) $xml['#']['import'];
                $imports[] = $nodeXml;
                $xml['#']['import'] = $imports;
            }

            if($configType === self::CONFIG_TYPE_SERVICE) {
                // Ensure services is array
                if(\is_string($xml['services']) && trim($xml['services']) === "") {
                    $xml['services'] = [];
                }

                // if only one service is defined, services plain array of that service
                // services => ['@id' => ..., ... ] so I need to change it in array of services
                if(array_key_exists('@id', $xml['services']['service'])) {
                    $xml['services']['service'] = [$xml['services']['service']];
                }

                $services = (array) $xml['services']['service'];
                $services[] = $nodeXml;
                $xml['services'] = $services;
            }

            $xmlEntry = $xmlEncoder->encode($xml, 'xml');
        }

        return $xmlEntry;
    }
}
