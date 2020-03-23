<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Operation;
use OpenApi\Annotations\Parameter;
use OpenApi\Annotations\Schema;
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

    public function load(string $api): OpenApi
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

        $allUndefined = true;
        $calculatedPaths = [];
        foreach ($openApi->paths as $pathItem) {
            foreach (self::OPERATION_KEYS as $key) {
                /** @var Operation $operation */
                $operation = $pathItem->$key;
                if ($operation instanceof Operation && !in_array(OpenApiSchemaBuilder::API[$api]['name'], $operation->tags, true)) {
                    $pathItem->$key = UNDEFINED;
                }

                if ($operation instanceof Operation && \count($operation->tags) > 1) {
                    foreach ($operation->tags as $tKey => $tag) {
                        if ($tag === OpenApiSchemaBuilder::API[$api]['name']) {
                            unset($operation->tags[$tKey]);
                        }
                    }

                    $operation->tags = array_values($operation->tags);
                }

                $allUndefined = ($pathItem->$key === UNDEFINED && $allUndefined === true);
            }

            if (!$allUndefined) {
                $calculatedPaths[] = $pathItem;
            }
        }
        $openApi->paths = $calculatedPaths;

        $this->replaceBasicApiParameter($openApi);

        return $openApi;
    }

    private function replaceBasicApiParameter(OpenApi $api): void
    {
        foreach ($api->paths as $path) {
            foreach (self::OPERATION_KEYS as $key) {
                /** @var Operation $operation */
                $operation = $path->$key;

                if (!$operation instanceof Operation) {
                    continue;
                }

                if ($operation->parameters === UNDEFINED) {
                    continue;
                }

                foreach ($operation->parameters as $parameterKey => $parameter) {
                    if ($parameter->name === 'Api-Basic-Parameters') {
                        unset($operation->parameters[$parameterKey]);

                        $limit = new Parameter([
                            'parameter' => 'limit',
                            'name' => 'limit',
                            'in' => 'query',
                            'description' => 'Limit',
                            'schema' => new Schema(['type' => 'integer']),
                        ]);

                        $page = new Parameter([
                            'parameter' => 'page',
                            'name' => 'page',
                            'in' => 'query',
                            'description' => 'page',
                            'schema' => new Schema(['type' => 'integer']),
                        ]);

                        $term = new Parameter([
                            'parameter' => 'term',
                            'name' => 'term',
                            'in' => 'query',
                            'description' => 'The term to search for',
                            'schema' => new Schema(['type' => 'string']),
                        ]);

                        $filter = new Parameter([
                            'parameter' => 'filter',
                            'name' => 'filter',
                            'in' => 'query',
                            'description' => 'Encoded SwagQL in JSON',
                            'schema' => new Schema(['type' => 'string']),
                        ]);

                        $aggregations = new Parameter([
                            'parameter' => 'aggregations',
                            'name' => 'aggregations',
                            'in' => 'query',
                            'description' => 'Encoded SwagQL in JSON',
                            'schema' => new Schema(['type' => 'string']),
                        ]);

                        $associations = new Parameter([
                            'parameter' => 'associations',
                            'name' => 'associations',
                            'in' => 'query',
                            'description' => 'Encoded SwagQL in JSON',
                            'schema' => new Schema(['type' => 'string']),
                        ]);

                        $postFilter = new Parameter([
                            'parameter' => 'post-filter',
                            'name' => 'post-filter',
                            'in' => 'query',
                            'description' => 'Encoded SwagQL in JSON',
                            'schema' => new Schema(['type' => 'string']),
                        ]);

                        $query = new Parameter([
                            'parameter' => 'query',
                            'name' => 'query',
                            'in' => 'query',
                            'description' => 'Encoded SwagQL in JSON',
                            'schema' => new Schema(['type' => 'string']),
                        ]);

                        $grouping = new Parameter([
                            'parameter' => 'grouping',
                            'name' => 'grouping',
                            'in' => 'query',
                            'description' => 'Encoded SwagQL in JSON',
                            'schema' => new Schema(['type' => 'string']),
                        ]);

                        array_unshift($operation->parameters, $page, $limit, $term, $filter, $postFilter, $associations, $aggregations, $query, $grouping);
                    }
                }
            }
        }
    }
}
