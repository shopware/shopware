<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use OpenApi\Annotations\JsonContent;
use OpenApi\Annotations\MediaType;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Operation;
use OpenApi\Annotations\PathItem;
use OpenApi\Annotations\Property;
use OpenApi\Annotations\RequestBody;
use OpenApi\Context;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\Event\OpenApiPathsEvent;
use Shopware\Core\Framework\Feature;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouterInterface;
use function OpenApi\scan;
use const OpenApi\Annotations\UNDEFINED;

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
     * @var RouterInterface
     */
    private $router;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(RouterInterface $router, EventDispatcherInterface $eventDispatcher)
    {
        $this->router = $router;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(string $api): OpenApi
    {
        $pathsToScan = array_unique(iterator_to_array($this->getApiRoutes(), false));
        $openApiPathsEvent = new OpenApiPathsEvent($pathsToScan);
        $this->eventDispatcher->dispatch($openApiPathsEvent);
        $openApi = scan($openApiPathsEvent->getPaths(), ['analysis' => new DeactivateValidationAnalysis()]);

        // @see: https://regex101.com/r/XYRxEm/1
        $sinceRegex = '/\@Since\("(.*)"\)/m';

        $calculatedPaths = [];
        foreach ($openApi->paths as $pathItem) {
            if (!$this->routeIsActive($pathItem)) {
                continue;
            }

            $allUndefined = true;

            foreach (self::OPERATION_KEYS as $key) {
                /** @var Operation $operation */
                $operation = $pathItem->$key;

                if ($operation instanceof Operation && !\in_array(OpenApiSchemaBuilder::API[$api]['name'], $operation->tags, true)) {
                    $pathItem->$key = UNDEFINED;
                }

                if ($operation instanceof Operation && \count($operation->tags) > 1) {
                    if ($api === 'store-api') {
                        if ($operation->security === UNDEFINED) {
                            $operation->security = [['ApiKey' => []]];
                        }

                        if (strpos($operation->_context->comment, '@LoginRequired') !== false) {
                            $operation->security[] = ['ContextToken' => []];
                        }
                    }

                    foreach ($operation->tags as $tKey => $tag) {
                        if ($tag === OpenApiSchemaBuilder::API[$api]['name']) {
                            unset($operation->tags[$tKey]);
                        }
                    }

                    preg_match($sinceRegex, $operation->_context->comment, $match);

                    if (\array_key_exists(1, $match)) {
                        $operation->description = 'Available since: ' . $match[1];
                    }

                    $operation->tags = array_values($operation->tags);
                }

                $allUndefined = $pathItem->$key === UNDEFINED && $allUndefined === true;
            }

            if (!$allUndefined) {
                $calculatedPaths[] = $pathItem;
            }
        }
        $openApi->paths = $calculatedPaths;

        $this->replaceBasicApiParameter($openApi);

        return $openApi;
    }

    private function getApiRoutes(): \Generator
    {
        foreach ($this->router->getRouteCollection() as $item) {
            $path = $item->getPath();
            if (
                strpos($path, '/api/') !== 0
                && strpos($path, '/store-api/') !== 0
            ) {
                continue;
            }

            $controllerClass = strtok($item->getDefault('_controller'), ':');
            $refClass = new \ReflectionClass($controllerClass);
            yield $refClass->getFileName();
        }
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

                        $operation->tags[] = 'supports Criteria filtering';

                        $limit = new Property([
                            'property' => 'limit',
                            'description' => 'Limit',
                            'type' => 'integer',
                        ]);

                        $page = new Property([
                            'property' => 'page',
                            'description' => 'page',
                            'type' => 'integer',
                        ]);

                        $term = new Property([
                            'property' => 'term',
                            'description' => 'The term to search for',
                            'type' => 'string',
                        ]);

                        $filter = new Property([
                            'property' => 'filter',
                            'description' => 'SwagQL',
                            'type' => 'array',
                            'items' => [
                                'properties' => [
                                    'type' => [
                                        'type' => 'string',
                                    ],
                                    'field' => [
                                        'type' => 'string',
                                    ],
                                    'value' => [
                                        'type' => 'string',
                                    ],
                                ],
                            ],
                        ]);

                        $postFilter = new Property([
                            'property' => 'post-filter',
                            'description' => 'SwagQL',
                            'type' => 'array',
                            'items' => [
                                'properties' => [
                                    'type' => [
                                        'type' => 'string',
                                    ],
                                    'field' => [
                                        'type' => 'string',
                                    ],
                                    'value' => [
                                        'type' => 'string',
                                    ],
                                ],
                            ],
                        ]);

                        $aggregations = new Property([
                            'property' => 'aggregations',
                            'description' => 'Encoded SwagQL in JSON',
                            'type' => 'array',
                            'items' => [
                                'properties' => [
                                    'name' => [
                                        'type' => 'string',
                                    ],
                                    'type' => [
                                        'type' => 'string',
                                    ],
                                    'field' => [
                                        'type' => 'string',
                                    ],
                                ],
                            ],
                        ]);

                        $associations = new Property([
                            'property' => 'associations',
                            'description' => 'Encoded SwagQL in JSON',
                            'type' => 'object',
                        ]);

                        $query = new Property([
                            'property' => 'query',
                            'description' => 'Encoded SwagQL in JSON',
                            'type' => 'array',
                            'items' => [
                                'properties' => [
                                    'score' => [
                                        'type' => 'integer',
                                    ],
                                    'query' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'type' => [
                                                'type' => 'string',
                                            ],
                                            'field' => [
                                                'type' => 'string',
                                            ],
                                            'value' => [
                                                'type' => 'string',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ]);

                        $sorting = new Property([
                            'property' => 'sort',
                            'description' => 'Encoded SwagQL in JSON',
                            'type' => 'array',
                            'items' => [
                                'properties' => [
                                    'field' => [
                                        'type' => 'string',
                                    ],
                                    'order' => [
                                        'type' => 'string',
                                    ],
                                    'naturalSorting' => [
                                        'type' => 'boolean',
                                    ],
                                ],
                            ],
                        ]);

                        $grouping = new Property([
                            'property' => 'grouping',
                            'description' => 'Encoded SwagQL in JSON',
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                        ]);

                        if ($operation->requestBody === UNDEFINED) {
                            $operation->requestBody = new RequestBody([
                                'required' => true,
                                'content' => [],
                            ]);
                        }

                        if (!isset($operation->requestBody->content['application/json'])) {
                            $operation->requestBody->content['application/json'] = new MediaType([
                                'mediaType' => 'application/json',
                                'schema' => new JsonContent([
                                    'properties' => [],
                                ]),
                            ]);
                        }

                        array_unshift($operation->requestBody->content['application/json']->schema->properties, $page, $limit, $term, $filter, $sorting, $postFilter, $associations, $aggregations, $query, $grouping);
                    }
                }

                $operation->parameters = array_values($operation->parameters);
            }
        }
    }

    /**
     * Check if route is annotated as internal and therefore inactive if not activated by a feature flag
     */
    private function routeIsActive(PathItem $item): bool
    {
        $docBlock = $item->_context->comment ?: '';
        $pattern = '#@([a-zA-Z]+)#';

        preg_match_all($pattern, $docBlock, $matches, \PREG_PATTERN_ORDER);

        if (!\in_array('internal', $matches[1], true)) {
            //get the comment from the Class
            if ($item->_context->with('comment') instanceof Context) {
                $classDocBlock = $item->_context->with('comment')->__get('comment') ?: '';
                $pattern = '#@([a-zA-Z]+)#';

                preg_match_all($pattern, $classDocBlock, $matches, \PREG_PATTERN_ORDER);

                if (\in_array('internal', $matches[1], true)) {
                    return $this->featureIsActive($classDocBlock);
                }
            }

            return true;
        }

        return $this->featureIsActive($docBlock);
    }

    private function featureIsActive(string $docBlock): bool
    {
        $flagPattern = "#@internal \(flag:([a-zA-Z_0-9]+)\)#";
        preg_match_all($flagPattern, $docBlock, $matches, \PREG_PATTERN_ORDER);

        if (\count($matches[1]) > 0 && strpos($matches[1][0], 'FEATURE_') === 0 && Feature::isActive($matches[1][0])) {
            return true;
        }

        return false;
    }
}
