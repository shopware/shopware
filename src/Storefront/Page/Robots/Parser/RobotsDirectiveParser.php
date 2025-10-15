<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots\Parser;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirective;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirectiveType;
use Shopware\Storefront\Page\Robots\Struct\RobotsUserAgentBlock;

#[Package('framework')]
class RobotsDirectiveParser
{
    public function parse(string $text): ParsedRobots
    {
        $lines = explode("\n", $text);
        $userAgentBlocks = [];
        $orphanedPathDirectives = [];
        $currentUserAgents = [];
        $currentDirectives = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Parse directive
            $parts = explode(':', $line, 2);
            if (\count($parts) !== 2) {
                continue;
            }

            $directiveType = trim($parts[0]);
            $directiveValue = trim($parts[1]);

            // Validate directive
            $directiveTypeEnum = RobotsDirectiveType::tryFromInsensitive($directiveType);
            if ($directiveTypeEnum === null) {
                continue;
            }

            // Use the canonical form from the enum
            $directiveType = $directiveTypeEnum->value;

            // Handle User-agent directive
            if ($directiveTypeEnum === RobotsDirectiveType::USER_AGENT) {
                // If we have a current block with directives, save it
                if (\count($currentUserAgents) > 0 && \count($currentDirectives) > 0) {
                    foreach ($currentUserAgents as $userAgent) {
                        $userAgentBlocks[] = new RobotsUserAgentBlock($userAgent, $currentDirectives);
                    }
                    // Reset for new block
                    $currentUserAgents = [];
                    $currentDirectives = [];
                }

                // Add user agent to current block
                $currentUserAgents[] = $directiveValue;

                continue;
            }

            // Handle other directives
            $directive = new RobotsDirective($directiveType, $directiveValue);

            if (\count($currentUserAgents) > 0) {
                // We're in a user-agent block
                $currentDirectives[] = $directive;
            } else {
                // Orphaned directive (backward compatibility)
                if ($directiveTypeEnum->isPathBased()) {
                    $orphanedPathDirectives[] = $directive;
                }
            }
        }

        // Save last block if any
        if (\count($currentUserAgents) > 0 && \count($currentDirectives) > 0) {
            foreach ($currentUserAgents as $userAgent) {
                $userAgentBlocks[] = new RobotsUserAgentBlock($userAgent, $currentDirectives);
            }
        }

        return new ParsedRobots($userAgentBlocks, $orphanedPathDirectives);
    }
}
