<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use OpenApi\Annotations\MediaType;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Operation;
use OpenApi\Annotations\PathItem;
use OpenApi\Annotations\RequestBody;
use OpenApi\Annotations\Schema;
use OpenApi\Context;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\Event\OpenApiPathsEvent;
use Shopware\Core\Framework\Feature;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use function OpenApi\scan;
use const OpenApi\Annotations\UNDEFINED;

/**
 * @deprecated tag:v6.5.0 - Will be removed
 */
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
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @internal
     */
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(string $api): OpenApi
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );
        $openApiPathsEvent = new OpenApiPathsEvent([]);
        $this->eventDispatcher->dispatch($openApiPathsEvent);
        if ($openApiPathsEvent->isEmpty()) {
            return new OpenApi([]);
        }

        $openApi = scan($openApiPathsEvent->getPaths(), ['analysis' => new DeactivateValidationAnalysis()]);

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
                        if (\strpos($operation->path, '/store-api') === 0) {
                            $operation->path = \substr($operation->path, \strlen('/store-api'));
                            $pathItem->path = \substr($pathItem->path, \strlen('/store-api'));
                        }

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

                        $operation->tags[] = 'Endpoints supporting Criteria ';

                        if ($operation->requestBody === UNDEFINED) {
                            $operation->requestBody = new RequestBody([
                                'required' => false,
                                'content' => [],
                            ]);
                        }

                        if (!isset($operation->requestBody->content['application/json'])) {
                            $operation->requestBody->content['application/json'] = new MediaType([
                                'mediaType' => 'application/json',
                            ]);
                        }

                        $allOf = [
                            ['$ref' => '#/components/schemas/Criteria'],
                        ];

                        if ($operation->requestBody->content['application/json']->schema !== UNDEFINED) {
                            $allOf[] = $operation->requestBody->content['application/json']->schema;
                        }

                        $operation->requestBody->content['application/json']->schema = new Schema([
                            'allOf' => $allOf,
                        ]);
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

        if (strpos($docBlock, '@internal') !== false) {
            return $this->featureIsActive($docBlock);
        }

        //get the comment from the Class
        $classContext = $item->_context->with('comment');
        if (!$classContext instanceof Context) {
            return true;
        }
        $classDocBlock = $classContext->__get('comment') ?: '';

        return $this->featureIsActive($classDocBlock);
    }

    private function featureIsActive(string $docBlock): bool
    {
        $internalPattern = '/@(internal.*)$/m';
        $flagPattern = "#\(flag:([a-zA-Z_0-9]+)\)#";

        if (!preg_match($internalPattern, $docBlock, $matches)) {
            return true;
        }
        $internalAnnotation = $matches[1];

        if (!preg_match($flagPattern, $internalAnnotation, $matches)) {
            return true;
        }

        return Feature::isActive($matches[1]);
    }
}
