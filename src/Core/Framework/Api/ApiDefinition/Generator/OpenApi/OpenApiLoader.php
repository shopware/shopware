<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Operation;
use OpenApi\Annotations\UNDEFINED;
use const OpenApi\Annotations\UNDEFINED;
use function OpenApi\scan;

class OpenApiLoader
{
    private const OPERATION_KEYS = [
        'get',
        'post',
        'put',
        'patch',
        'delete',
    ];

    /**
     * @var string
     */
    private $rootDir;

    public function __construct(string $rootDir)
    {
        $this->rootDir = $rootDir;
    }

    public function load(bool $forSalesChannel): OpenApi
    {
        $pathsToScan = [
            // project src
            $this->rootDir . '/src',
            // platform or many repos
            $this->rootDir . '/vendor/shopware',
            // plugins
            $this->rootDir . '/custom/plugins',
        ];
        $openApi = scan($pathsToScan, ['analysis' => new DeactivateValidationAnalysis()]);

        foreach ($openApi->paths as $pathItem) {
            foreach (self::OPERATION_KEYS as $key) {
                /** @var Operation $operation */
                $operation = $pathItem->$key;
                if ($operation instanceof Operation && !in_array($this->getTagForApi($forSalesChannel), $operation->tags, true)) {
                    $pathItem->$key = UNDEFINED;
                }
            }
        }

        return $openApi;
    }

    private function getTagForApi(bool $forSalesChannel): string
    {
        return $forSalesChannel ? 'Sales Channel Api' : 'Admin Api';
    }
}
