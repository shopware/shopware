<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Agent;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
class OpenAiLayoutAgentClient
{
    /**
     * @param array<array{role: string, content: string}> $messages
     *
     * @return array{message: string, layout: array<mixed>|null}
     */
    public function respond(array $messages, array $layout, ?string $rootSource, ?string $selectedElementId, array $elementTypes, array $styleOptions): array
    {
        if ($this->apiKey === '') {
            throw new \LogicException('The Experience Studio agent is not configured.');
        }

        $this->nativeMcpToolClient->resetSession();

        $input = [
            ...$messages,
            [
                'role' => 'developer',
                'content' => json_encode([
                    'instruction' => 'CURRENT_LAYOUT_SNAPSHOT: This snapshot supersedes every earlier layout or element state mentioned in the conversation. Manual editor changes may have occurred since the previous agent turn. Use only IDs and values present here.',
                    'layoutFingerprint' => hash('sha256', json_encode($layout, \JSON_THROW_ON_ERROR)),
                    'layout' => $layout,
                    'rootSource' => $rootSource,
                    'uiSelection' => [
                        'instruction' => 'Editor context only. Do not assume this is the requested target. Use it only when the user explicitly refers to the selected element, "this element", or equivalent wording.',
                        'elementId' => $selectedElementId,
                        'element' => $this->findElement($layout, $selectedElementId),
                    ],
                    'elementTypes' => $elementTypes,
                    'styleOptions' => $styleOptions,
                ], \JSON_THROW_ON_ERROR),
            ],
        ];
        $latestLayout = null;
        for ($turn = 0; $turn < 5; ++$turn) {
            $response = $this->requestResponse($input);
            $functionCalls = array_values(array_filter(
                $response['output'] ?? [],
                static fn (mixed $output): bool => \is_array($output) && ($output['type'] ?? null) === 'function_call',
            ));

            if ($functionCalls === []) {
                return [
                    'message' => $this->extractMessage($response),
                    'layout' => $latestLayout,
                ];
            }

            $input = [...$input, ...$response['output']];
            foreach ($functionCalls as $functionCall) {
                $output = $this->callTool($functionCall, $latestLayout ?? $layout, $rootSource);
                $decodedOutput = json_decode($output, true);
                if (!\is_array($decodedOutput) || ($decodedOutput['success'] ?? false) !== true) {
                    return [
                        'message' => \is_string($decodedOutput['error'] ?? null)
                            ? 'I could not apply that layout change: ' . $decodedOutput['error']
                            : 'I could not apply that layout change.',
                        'layout' => $latestLayout,
                    ];
                }

                if (\is_array($decodedOutput['data']['layout'] ?? null)) {
                    $latestLayout = $decodedOutput['data']['layout'];
                }

                $input[] = [
                    'type' => 'function_call_output',
                    'call_id' => $functionCall['call_id'],
                    'output' => $output,
                ];
            }
        }

        return [
            'message' => 'The agent reached its maximum number of tool calls.',
            'layout' => $latestLayout,
        ];
    }

    /**
     * @param array<mixed> $input
     *
     * @return array<mixed>
     */
    private function requestResponse(array $input): array
    {
        return $this->httpClient->request('POST', 'https://api.openai.com/v1/responses', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-5.6-luna',
                'instructions' => <<<'INSTRUCTIONS'
You are the Shopware Experience Studio layout agent. You help administrators create, understand, and improve the currently open Shopware Content System layout.

The current draft layout, its root source, selected element, available element types, and universal style options are supplied in the conversation context.
Treat that draft as the source of truth. Changes must remain in the draft: never claim that a layout has been saved or published.
The CURRENT_LAYOUT_SNAPSHOT is freshly supplied for every turn and always supersedes earlier conversation state. The administrator may have manually edited, moved, deleted, or configured elements between turns. Never reuse an earlier element state or ID unless it is still present in the current snapshot.

Use only the supplied Shopware MCP tools to inspect, diagnose, preview, or change a layout. Do not invent tool results, element IDs, element types, binding specifications, entities, or successful changes.
For additions containing multiple nested elements, prefer the compose tool so structure and configuration are completed atomically in one call. For every requested visual change, complete both the structure and configuration. After a standalone insertion, call the configure tool to set requested authored content and visual values. Container backgroundColor and backgroundOpacity are element properties. Text content belongs in the text element's text property and may contain sanitized HTML. Universal alignment options belong in style and breakpoint-aware values must use breakpoint maps such as {"justify-self":{"xs":"center"}}.
For follow-up requests that only change content or appearance, use the configure tool on the existing element IDs. Do not use structural operations such as wrap, replace, or insert unless the requested structure actually changes. The wrap operation requires elementIds to be a JSON list of existing element ID strings and slot to be the valid receiving slot from the chosen container's element-type specification.
Derive the target element from the current user request and the current layout. The uiSelection is only an editor hint: use it when the user explicitly refers to the selected element or "this element", but never as a fallback for another missing or uncertain target. If more than one existing element plausibly matches the request, ask one concise clarifying question and do not call a mutation tool.
When the user asks for an existing image or describes media to use, call the media search tool. Choose only from its returned media records, then configure the image element with the returned media ID using the storage property declared by the element-type specification, usually mediaId. Never invent a media ID.
After every mutation or configuration, use the returned layout as the new source of truth. Complete all parts of the request before replying.

Explain the a short summary of the outcomes to the administrator in the response message. Don't add validation or workflow info.
The administrator controls saving the layout in Experience Studio.
INSTRUCTIONS,
                'input' => $input,
                'tools' => [[
                    'type' => 'function',
                    'name' => 'shopware-content-layout-mutate',
                    'description' => 'Apply an explicitly requested structural change to the current draft layout. Never use insert or replace for a follow-up that only changes an existing element.',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['operation', 'request'],
                        'properties' => [
                            'operation' => ['type' => 'string', 'enum' => ['insert', 'remove', 'move', 'replace', 'duplicate', 'wrap', 'unwrap', 'attach', 'bind']],
                            'request' => [
                                'type' => 'object',
                                'description' => 'The current layout, rootSource, and operation arguments. For insert, include type. For remove/duplicate/move/replace/bind include elementId. For replace include newType. For wrap, elementIds, containerType, and a valid container slot are mandatory. For unwrap include containerElementId. For attach include element.',
                                'properties' => [
                                    'layout' => ['type' => 'array'],
                                    'rootSource' => ['type' => ['string', 'null']],
                                    'type' => ['type' => 'string'],
                                    'elementId' => ['type' => 'string'],
                                    'newType' => ['type' => 'string'],
                                    'parentElementId' => ['type' => ['string', 'null']],
                                    'slot' => [
                                        'type' => ['string', 'null'],
                                        'description' => 'For wrap this is mandatory and must name the container slot that receives the wrapped elements. Derive it from the container element-type specification.',
                                    ],
                                    'index' => ['type' => ['integer', 'null']],
                                    'newParentId' => ['type' => ['string', 'null']],
                                    'newSlot' => ['type' => ['string', 'null']],
                                    'elementIds' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string'],
                                    ],
                                    'containerType' => ['type' => 'string'],
                                    'containerElementId' => ['type' => 'string'],
                                    'element' => ['type' => 'object'],
                                    'bindingSpecificationId' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ], [
                    'type' => 'function',
                    'name' => 'shopware-content-media-search',
                    'description' => 'Search the Shopware media library for an existing image by filename, title, alt text, or descriptive term. Returns media IDs that can be assigned to image elements.',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['query'],
                        'properties' => [
                            'query' => ['type' => 'string'],
                            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 25],
                        ],
                    ],
                ], [
                    'type' => 'function',
                    'name' => 'shopware-content-layout-compose',
                    'description' => 'Create and configure multiple nested draft elements only when the current user request explicitly asks to add new content. Never use this to modify an existing or selected element.',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['insertions'],
                        'properties' => [
                            'insertions' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'required' => ['alias', 'type'],
                                    'properties' => [
                                        'alias' => ['type' => 'string'],
                                        'type' => ['type' => 'string'],
                                        'parentAlias' => ['type' => 'string'],
                                        'parentElementId' => ['type' => ['string', 'null']],
                                        'slot' => ['type' => ['string', 'null']],
                                        'index' => ['type' => ['integer', 'null']],
                                        'bindingSpecificationId' => ['type' => 'string'],
                                        'properties' => ['type' => 'object'],
                                        'style' => ['type' => 'object'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], [
                    'type' => 'function',
                    'name' => 'shopware-content-layout-configure',
                    'description' => 'Set authored properties and universal style options on the existing draft element identified from the user request. Do not guess or substitute the editor selection when the target is ambiguous; ask the user instead.',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['elementId'],
                        'properties' => [
                            'elementId' => ['type' => 'string'],
                            'properties' => ['type' => 'object'],
                            'style' => ['type' => 'object'],
                        ],
                    ],
                ], [
                    'type' => 'function',
                    'name' => 'shopware-content-layout-diagnose',
                    'description' => 'Validate an Experience Studio draft layout without changing it.',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['layout'],
                        'properties' => [
                            'layout' => ['type' => 'string'],
                            'rootSource' => ['type' => ['string', 'null']],
                        ],
                    ],
                ], [
                    'type' => 'function',
                    'name' => 'shopware-content-layout-preview',
                    'description' => 'Render an Experience Studio draft layout against a real entity without saving it.',
                    'parameters' => [
                        'type' => 'object',
                        'required' => ['layout', 'entityType', 'entityId', 'salesChannelId'],
                        'properties' => [
                            'layout' => ['type' => 'string'],
                            'entityType' => ['type' => 'string'],
                            'entityId' => ['type' => 'string'],
                            'salesChannelId' => ['type' => 'string'],
                            'languageId' => ['type' => ['string', 'null']],
                            'currencyId' => ['type' => ['string', 'null']],
                            'domainId' => ['type' => ['string', 'null']],
                            'customerId' => ['type' => ['string', 'null']],
                        ],
                    ],
                ]],
            ],
        ])->toArray();
    }

    /**
     * @param array<mixed> $functionCall
     */
    private function callTool(array $functionCall, array $currentLayout, ?string $rootSource): string
    {
        if (!\is_string($functionCall['arguments'] ?? null)) {
            return json_encode(['success' => false, 'error' => 'The requested tool is not available.'], \JSON_THROW_ON_ERROR);
        }

        $arguments = json_decode($functionCall['arguments'], true);
        if (!\is_array($arguments)) {
            return json_encode(['success' => false, 'error' => 'Invalid tool arguments.'], \JSON_THROW_ON_ERROR);
        }

        $name = $functionCall['name'] ?? null;
        if (!\is_string($name) || !\in_array($name, ['shopware-content-layout-mutate', 'shopware-content-layout-compose', 'shopware-content-layout-configure', 'shopware-content-layout-diagnose', 'shopware-content-layout-preview', 'shopware-content-media-search'], true)) {
            return json_encode(['success' => false, 'error' => 'The requested tool is not available.'], \JSON_THROW_ON_ERROR);
        }

        if ($name === 'shopware-content-layout-mutate' && \is_array($arguments['request'] ?? null)) {
            if (($arguments['operation'] ?? null) === 'wrap') {
                if (\is_string($arguments['request']['elementIds'] ?? null)) {
                    $arguments['request']['elementIds'] = [$arguments['request']['elementIds']];
                } elseif (!isset($arguments['request']['elementIds']) && \is_string($arguments['request']['elementId'] ?? null)) {
                    $arguments['request']['elementIds'] = [$arguments['request']['elementId']];
                }
            }

            $arguments['request']['layout'] = $currentLayout;
            $arguments['request']['rootSource'] = $rootSource;
            $arguments['request'] = json_encode($arguments['request'], \JSON_THROW_ON_ERROR);
        }

        if ($name === 'shopware-content-layout-configure') {
            $arguments['layout'] = json_encode($currentLayout, \JSON_THROW_ON_ERROR);
            $arguments['properties'] = json_encode(\is_array($arguments['properties'] ?? null) ? $arguments['properties'] : [], \JSON_THROW_ON_ERROR);
            $arguments['style'] = json_encode(\is_array($arguments['style'] ?? null) ? $arguments['style'] : [], \JSON_THROW_ON_ERROR);
        }

        if ($name === 'shopware-content-layout-compose') {
            $arguments['layout'] = json_encode($currentLayout, \JSON_THROW_ON_ERROR);
            $arguments['rootSource'] = $rootSource;
            $arguments['insertions'] = json_encode(\is_array($arguments['insertions'] ?? null) ? $arguments['insertions'] : [], \JSON_THROW_ON_ERROR);
        }

        if (($name === 'shopware-content-layout-diagnose' || $name === 'shopware-content-layout-preview')) {
            $arguments['layout'] = json_encode($currentLayout, \JSON_THROW_ON_ERROR);
        }

        if ($name === 'shopware-content-media-search') {
            return $this->nativeMcpToolClient->call('shopware-entity-search', [
                'entity' => 'media',
                'term' => \is_string($arguments['query'] ?? null) ? $arguments['query'] : '',
                'limit' => \is_int($arguments['limit'] ?? null) ? min(25, max(1, $arguments['limit'])) : 10,
                'criteria' => json_encode([
                    'includes' => [
                        'media' => ['id', 'fileName', 'title', 'alt', 'mimeType', 'fileExtension', 'createdAt'],
                    ],
                ], \JSON_THROW_ON_ERROR),
            ]);
        }

        return $this->nativeMcpToolClient->call($name, $arguments);
    }

    /**
     * @param array<mixed> $elements
     *
     * @return array<mixed>|null
     */
    private function findElement(array $elements, mixed $elementId): ?array
    {
        if (!\is_string($elementId)) {
            return null;
        }

        foreach ($elements as $element) {
            if (!\is_array($element)) {
                continue;
            }

            if (($element['id'] ?? null) === $elementId) {
                return $element;
            }

            foreach ($element['slots'] ?? [] as $slotElements) {
                if (!\is_array($slotElements)) {
                    continue;
                }

                $found = $this->findElement($slotElements, $elementId);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $response
     */
    private function extractMessage(array $response): string
    {
        $message = '';
        foreach ($response['output'] ?? [] as $output) {
            if (($output['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ($output['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text') {
                    $message .= $content['text'] ?? '';
                }
            }
        }

        return $message !== '' ? $message : 'The agent did not return a response.';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @internal
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly NativeMcpToolClient $nativeMcpToolClient,
    ) {
    }
}
