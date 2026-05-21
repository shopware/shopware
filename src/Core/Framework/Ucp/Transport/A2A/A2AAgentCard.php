<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\A2A;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\CapabilityIntersection;
use Shopware\Core\Framework\Ucp\Capability\CapabilityRegistry;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Builds an [A2A Agent Card](https://a2a-protocol.org/topics/agent-cards/) for
 * the business per UCP `checkout-a2a.md` §"Transport Discovery". The card is
 * served at `/.well-known/agent-card.json` and consumed by A2A-aware
 * platforms to discover endpoints + activate the UCP extension.
 *
 * Spec-required pieces:
 *   - `name`, `description`, `version`
 *   - `endpoints` — array containing the A2A JSON-RPC URL
 *   - `extensions[]` — at least one entry with the UCP extension URI and
 *     the negotiated `capabilities` map
 *
 * @internal
 */
#[Package('framework')]
class A2AAgentCard
{
    public function __construct(
        private readonly CapabilityRegistry $capabilityRegistry,
    ) {
    }

    /**
     * @param list<string> $enabledCapabilities
     *
     * @return array<string, mixed>
     */
    public function build(string $baseUri, string $ucpVersion, array $enabledCapabilities): array
    {
        $caps = [];
        foreach ($enabledCapabilities as $capName) {
            $cap = $this->capabilityRegistry->get($capName);
            if ($cap === null) {
                continue;
            }
            $caps[$capName] = [[
                'version' => $cap->getVersion(),
            ]];
        }

        return [
            'agent_card_version' => '0.1',
            'name' => 'Shopware UCP Agent',
            'description' => 'A2A interface for the Universal Commerce Protocol business surface',
            'version' => '1.0.0',
            'protocol_version' => '0.1',
            'endpoints' => [
                'jsonrpc' => rtrim($baseUri, '/') . '/ucp/a2a',
            ],
            'capabilities' => [
                'streaming' => false,
                'state_transitions' => true,
            ],
            'default_input_modes' => ['text/plain', 'application/json'],
            'default_output_modes' => ['application/json'],
            'extensions' => [
                [
                    'uri' => 'https://ucp.dev/' . $ucpVersion . '/specification/reference',
                    'description' => 'Business agent supporting UCP',
                    'params' => [
                        'capabilities' => $caps,
                    ],
                ],
            ],
        ];
    }

    /**
     * Build an Agent Card for a known intersection (used by the live JSON-RPC
     * handler to report only what the platform actually negotiated, not the
     * superset of all capabilities the business COULD support).
     */
    /**
     * @return array<string, mixed>
     */
    public function buildForIntersection(string $baseUri, CapabilityIntersection $intersection): array
    {
        $caps = [];
        foreach ($intersection->toArray() as $name => $entries) {
            $caps[$name] = $entries;
        }

        return $this->build($baseUri, $intersection->protocolVersion, []) + [
            'extensions' => [[
                'uri' => 'https://ucp.dev/' . $intersection->protocolVersion . '/specification/reference',
                'description' => 'Business agent supporting UCP',
                'params' => ['capabilities' => $caps],
            ]],
        ];
    }
}
