<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Mcp\Resource;

use Mcp\Capability\Attribute\McpResourceTemplate;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Result\McpToolResultPointer;
use Shopware\Core\Framework\Mcp\Result\McpToolResultPrincipal;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @experimental stableVersion:v6.8.0
 *
 * The Store API counterpart of {@see \Shopware\Core\Framework\Mcp\Resource\ToolResultResource}. The
 * signed pointer only works in the sales-channel context that stored the result.
 */
#[Package('framework')]
#[McpResourceTemplate(
    uriTemplate: McpToolResultPointer::URI_PREFIX . '{id}',
    name: 'tool-result',
    description: 'A large tool result that a previous tool call stored instead of returning it inline. Tool results point here with a resource_link. The link expires after an hour.',
    mimeType: 'application/json',
)]
class StoreApiToolResultResource
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ToolResultCacheStorage $storage,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(string $id): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $principal = $request !== null ? McpToolResultPrincipal::fromRequest($request) : null;
        $result = $principal !== null ? $this->storage->readFor($id, $principal) : null;

        if ($result === null) {
            throw SalesChannelException::mcpToolResultNotFound($id);
        }

        return [
            'uri' => McpToolResultPointer::URI_PREFIX . $id,
            'mimeType' => $result['mimeType'],
            'text' => $result['content'],
        ];
    }
}
