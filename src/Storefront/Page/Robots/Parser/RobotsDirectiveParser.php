<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots\Parser;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Page\Robots\Event\RobotsDirectiveParsingEvent;
use Shopware\Storefront\Page\Robots\Event\RobotsUnknownDirectiveEvent;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirective;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirectiveType;
use Shopware\Storefront\Page\Robots\Struct\RobotsUserAgentBlock;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('framework')]
class RobotsDirectiveParser
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function parse(string $text, Context $context, ?string $salesChannelId = null): ParsedRobots
    {
        $lines = explode("\n", $text);
        $userAgentBlocks = [];
        $orphanedPathDirectives = [];
        $currentUserAgents = [];
        $currentDirectives = [];
        $issues = [];
        $lineNumber = 0;

        foreach ($lines as $line) {
            ++$lineNumber;
            $line = trim($line);

            // Skip empty lines and comments
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Parse directive
            $parts = explode(':', $line, 2);
            if (\count($parts) !== 2) {
                $issues[] = new ParseIssue(
                    $lineNumber,
                    $line,
                    'Malformed line: missing colon separator',
                    ParseIssueSeverity::ERROR
                );

                continue;
            }

            $directiveType = trim($parts[0]);
            $directiveValue = trim($parts[1]);

            // Validate directive
            $directiveTypeEnum = RobotsDirectiveType::tryFromInsensitive($directiveType);
            if ($directiveTypeEnum === null) {
                // Dispatch event for unknown directive
                $unknownDirectiveEvent = new RobotsUnknownDirectiveEvent(
                    $lineNumber,
                    $line,
                    $directiveType,
                    $directiveValue,
                    \count($currentUserAgents) > 0,
                    $context,
                    $salesChannelId
                );
                $this->eventDispatcher->dispatch($unknownDirectiveEvent);

                // If the event was handled, skip warning; otherwise add custom issue or default warning
                if (!$unknownDirectiveEvent->handled) {
                    $issue = $unknownDirectiveEvent->issue;
                    if ($issue === null) {
                        $issue = new ParseIssue(
                            $lineNumber,
                            $line,
                            'Unknown directive type: \'' . $directiveType . '\'',
                            ParseIssueSeverity::WARNING
                        );
                    }
                    $issues[] = $issue;
                }

                continue;
            }

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
            $directive = new RobotsDirective($directiveTypeEnum, $directiveValue);

            if (\count($currentUserAgents) > 0) {
                // We're in a user-agent block
                $currentDirectives[] = $directive;
            } else {
                // Orphaned directive (backward compatibility)
                if ($directiveTypeEnum->isPathBased()) {
                    $orphanedPathDirectives[] = $directive;
                } else {
                    $issues[] = new ParseIssue(
                        $lineNumber,
                        $line,
                        'Directive \'' . $directiveTypeEnum->value . '\' found outside user-agent block and will be ignored',
                        ParseIssueSeverity::WARNING
                    );
                }
            }
        }

        // Save last block if any
        if (\count($currentUserAgents) > 0 && \count($currentDirectives) > 0) {
            foreach ($currentUserAgents as $userAgent) {
                $userAgentBlocks[] = new RobotsUserAgentBlock($userAgent, $currentDirectives);
            }
        }

        $parsedResult = new ParsedRobots($userAgentBlocks, $orphanedPathDirectives, $issues);

        // Dispatch parsing event to allow modifications
        $parsingEvent = new RobotsDirectiveParsingEvent($text, $parsedResult, $context, $salesChannelId);
        $this->eventDispatcher->dispatch($parsingEvent);

        return $parsingEvent->parsedResult;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed with next major, as string parsing is deprecated
     */
    public static function parseDirectiveFromString(string $line): ?RobotsDirective
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'the `parse()` method of the `RobotsDirectiveParser` service to parse the whole `robots.txt` file')
        );

        $parts = explode(':', $line, 2);

        $directiveType = mb_strtolower($parts[0] ?? '');
        $directiveTypeEnum = RobotsDirectiveType::tryFromInsensitive($directiveType);

        if ($directiveTypeEnum === null || !$directiveTypeEnum->isPathBased()) {
            return null;
        }

        return new RobotsDirective($directiveTypeEnum, trim($parts[1] ?? ''));
    }
}
