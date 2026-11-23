<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResourceTemplate;
use Mcp\Server\RequestContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpException;
use Shopware\Core\Framework\Mcp\Result\McpToolResultPointer;
use Shopware\Core\Framework\Mcp\Result\McpToolResultPrincipal;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Serves a large tool result that a previous tool call stored. The id in the URI is a signed pointer
 * that only the integration or user who stored the result can read. A plain id, as issued before the
 * pointers were signed, is still served to the session that stored it.
 */
#[Package('framework')]
#[McpResourceTemplate(
    uriTemplate: McpToolResultPointer::URI_PREFIX . '{id}',
    name: 'tool-result',
    description: 'A large tool result that a previous tool call stored instead of returning it inline. Tool results point here with a resource_link. The link expires after an hour.',
    mimeType: 'application/json',
)]
class ToolResultResource
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
    public function __invoke(string $id, RequestContext $context): array
    {
        if (str_contains($id, '.')) {
            $request = $this->requestStack->getCurrentRequest();
            $principal = $request !== null ? McpToolResultPrincipal::fromRequest($request) : null;
            $result = $principal !== null ? $this->storage->readFor($id, $principal) : null;
        } else {
            $result = $this->storage->read($id, $context->getSession()->getId()->toString());
        }

        if ($result === null) {
            throw McpException::toolResultNotFound($id);
        }

        return [
            'uri' => McpToolResultPointer::URI_PREFIX . $id,
            'mimeType' => $result['mimeType'],
            'text' => $result['content'],
        ];
    }
}
