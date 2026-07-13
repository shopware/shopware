<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mcp;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Api\DuplicateElementRequest;
use Shopware\Core\Framework\ContentSystem\Api\InsertElementRequest;
use Shopware\Core\Framework\ContentSystem\Api\LayoutMutationController;
use Shopware\Core\Framework\ContentSystem\Api\MoveElementRequest;
use Shopware\Core\Framework\ContentSystem\Api\RemoveElementRequest;
use Shopware\Core\Framework\ContentSystem\Api\ReplaceElementRequest;
use Shopware\Core\Framework\ContentSystem\Api\WrapElementsRequest;
use Shopware\Core\Framework\ContentSystem\Api\UnwrapElementRequest;
use Shopware\Core\Framework\ContentSystem\Api\AttachElementRequest;
use Shopware\Core\Framework\ContentSystem\Api\BindElementRequest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

/**
 * @internal
 */
#[McpTool(name: 'shopware-content-layout-mutate', title: 'Mutate content layout draft', description: 'Use this tool to insert, remove, move, replace, duplicate, wrap, unwrap, attach, or bind elements in the current Experience Studio draft layout. The tool never persists the layout; use its returned layout in the editor draft.')]
#[McpToolRequires('content_layout:update')]
#[Package('framework')]
class ContentSystemLayoutMutationTool extends McpToolResponse
{
    /**
     * @internal
     */
    public function __construct(
        private readonly LayoutMutationController $controller,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(string $operation, string $request): string
    {
        $context = $this->contextProvider->getContext();
        if ($error = $this->requirePrivilege($context, 'content_layout:update')) {
            return $error;
        }

        $payload = $this->decodeJsonOrError($request, 'request');
        if (\is_string($payload)) {
            return $payload;
        }

        try {
            $response = match ($operation) {
                'insert' => $this->controller->insert(new InsertElementRequest(
                    $this->string($payload, 'type'),
                    $this->layout($payload),
                    $this->nullableString($payload, 'parentElementId'),
                    $this->nullableString($payload, 'slot'),
                    $this->nullableInt($payload, 'index'),
                    $this->nullableString($payload, 'rootSource'),
                    $this->nullableString($payload, 'bindingSpecificationId'),
                ), $context),
                'remove' => $this->controller->remove(new RemoveElementRequest(
                    $this->string($payload, 'elementId'),
                    $this->layout($payload),
                    $this->nullableString($payload, 'rootSource'),
                ), $context),
                'move' => $this->controller->move(new MoveElementRequest(
                    $this->string($payload, 'elementId'),
                    $this->layout($payload),
                    $this->nullableString($payload, 'newParentId'),
                    $this->nullableString($payload, 'newSlot'),
                    $this->nullableInt($payload, 'index'),
                    $this->nullableString($payload, 'rootSource'),
                ), $context),
                'duplicate' => $this->controller->duplicate(new DuplicateElementRequest(
                    $this->string($payload, 'elementId'),
                    $this->layout($payload),
                    $this->nullableInt($payload, 'index'),
                    $this->nullableString($payload, 'rootSource'),
                ), $context),
                'replace' => $this->controller->replace(new ReplaceElementRequest(
                    $this->string($payload, 'elementId'),
                    $this->string($payload, 'newType'),
                    $this->layout($payload),
                    $this->nullableString($payload, 'rootSource'),
                ), $context),
                'wrap' => $this->controller->wrap(new WrapElementsRequest(
                    $this->stringList($payload, 'elementIds'),
                    $this->string($payload, 'containerType'),
                    $this->layout($payload),
                    $this->nullableString($payload, 'slot'),
                    $this->nullableString($payload, 'rootSource'),
                ), $context),
                'unwrap' => $this->controller->unwrap(new UnwrapElementRequest(
                    $this->string($payload, 'containerElementId'),
                    $this->layout($payload),
                    $this->nullableString($payload, 'rootSource'),
                ), $context),
                'attach' => $this->controller->attach(new AttachElementRequest(
                    $this->object($payload, 'element'),
                    $this->layout($payload),
                    $this->nullableString($payload, 'parentElementId'),
                    $this->nullableString($payload, 'slot'),
                    $this->nullableInt($payload, 'index'),
                    $this->nullableString($payload, 'rootSource'),
                ), $context),
                'bind' => $this->controller->bind(new BindElementRequest(
                    $this->string($payload, 'elementId'),
                    $this->string($payload, 'bindingSpecificationId'),
                    $this->layout($payload),
                    $this->nullableString($payload, 'rootSource'),
                ), $context),
                default => throw new \InvalidArgumentException('Unsupported content layout operation.'),
            };
        } catch (ContentSystemException|\InvalidArgumentException $e) {
            return $this->error($e->getMessage());
        }

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $this->success($data);
    }

    /**
     * @param array<mixed> $payload
     */
    private function string(array $payload, string $key): string
    {
        if (!\is_string($payload[$key] ?? null) || $payload[$key] === '') {
            throw new \InvalidArgumentException(\sprintf('"%s" is required.', $key));
        }

        return $payload[$key];
    }

    /**
     * @param array<mixed> $payload
     *
     * @return array<mixed>
     */
    private function layout(array $payload): array
    {
        if (!\is_array($payload['layout'] ?? null)) {
            throw new \InvalidArgumentException('"layout" is required.');
        }

        return $payload['layout'];
    }

    /**
     * @param array<mixed> $payload
     */
    private function nullableString(array $payload, string $key): ?string
    {
        return \is_string($payload[$key] ?? null) ? $payload[$key] : null;
    }

    /**
     * @param array<mixed> $payload
     */
    private function nullableInt(array $payload, string $key): ?int
    {
        return \is_int($payload[$key] ?? null) ? $payload[$key] : null;
    }

    /**
     * @param array<mixed> $payload
     *
     * @return list<string>
     */
    private function stringList(array $payload, string $key): array
    {
        if (!\is_array($payload[$key] ?? null) || !array_is_list($payload[$key])) {
            throw new \InvalidArgumentException(\sprintf('"%s" must be a list of strings.', $key));
        }

        foreach ($payload[$key] as $value) {
            if (!\is_string($value) || $value === '') {
                throw new \InvalidArgumentException(\sprintf('"%s" must be a list of strings.', $key));
            }
        }

        return $payload[$key];
    }

    /**
     * @param array<mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function object(array $payload, string $key): array
    {
        if (!\is_array($payload[$key] ?? null) || array_is_list($payload[$key])) {
            throw new \InvalidArgumentException(\sprintf('"%s" must be an object.', $key));
        }

        return $payload[$key];
    }
}
