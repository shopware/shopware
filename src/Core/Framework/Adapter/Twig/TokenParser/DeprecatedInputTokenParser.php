<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\TokenParser;

use Shopware\Core\Framework\Adapter\Twig\Exception\DeprecatedInputSyntaxError;
use Shopware\Core\Framework\Adapter\Twig\Node\DeprecatedInputNode;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * @internal
 */
#[Package('framework')]
final class DeprecatedInputTokenParser extends AbstractTokenParser
{
    private const OPTIONS = ['replaced_by', 'message', 'removed_in'];

    public function parse(Token $token): DeprecatedInputNode
    {
        $stream = $this->parser->getStream();
        $kind = $stream->expect(Token::NAME_TYPE);

        if ($kind->getValue() !== 'input') {
            DeprecatedInputSyntaxError::raise('The "sw_deprecated" tag currently supports only input declarations.', $kind->getLine(), $stream->getSourceContext());
        }

        $pathToken = $stream->expect(Token::STRING_TYPE);
        $path = $pathToken->getValue();
        if (!\is_string($path) || !\preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/D', $path)) {
            DeprecatedInputSyntaxError::raise('A deprecated input must be a literal root or dotted path.', $pathToken->getLine(), $stream->getSourceContext());
        }

        $options = [];
        while (!$stream->test(Token::BLOCK_END_TYPE)) {
            $name = $stream->expect(Token::NAME_TYPE);
            $option = $name->getValue();
            if (!\is_string($option) || !\in_array($option, self::OPTIONS, true)) {
                DeprecatedInputSyntaxError::raise(\sprintf('Unknown option "%s" for the "sw_deprecated" tag.', (string) $option), $name->getLine(), $stream->getSourceContext());
            }

            if (\array_key_exists($option, $options)) {
                DeprecatedInputSyntaxError::raise(\sprintf('The option "%s" is declared more than once.', $option), $name->getLine(), $stream->getSourceContext());
            }

            $stream->expect(Token::OPERATOR_TYPE, '=');
            $value = $stream->expect(Token::STRING_TYPE);
            $options[$option] = $value->getValue();
        }

        $stream->expect(Token::BLOCK_END_TYPE);

        $removedIn = $options['removed_in'] ?? null;
        if (!\is_string($removedIn)) {
            DeprecatedInputSyntaxError::raise('A deprecated input requires the "removed_in" option.', $token->getLine(), $stream->getSourceContext());
        }

        $feature = Feature::getRegisteredFeatures()[Feature::normalizeName($removedIn)] ?? null;
        if (!\preg_match('/^v\d+\.\d+\.\d+\.\d+$/D', $removedIn) || $feature === null || !($feature['major'] ?? false)) {
            DeprecatedInputSyntaxError::raise(\sprintf('The "removed_in" option must name a canonical registered major feature flag, got "%s".', $removedIn), $token->getLine(), $stream->getSourceContext());
        }

        $replacedBy = $options['replaced_by'] ?? null;
        $message = $options['message'] ?? null;
        if (\is_string($replacedBy) === \is_string($message)) {
            DeprecatedInputSyntaxError::raise('A deprecated input requires exactly one of "replaced_by" or "message".', $token->getLine(), $stream->getSourceContext());
        }

        if (\is_string($replacedBy)) {
            if (!\preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/D', $replacedBy)) {
                DeprecatedInputSyntaxError::raise('The "replaced_by" option must be a literal root or dotted path.', $token->getLine(), $stream->getSourceContext());
            }

            if ($replacedBy === $path) {
                DeprecatedInputSyntaxError::raise('The replacement must differ from the deprecated input path.', $token->getLine(), $stream->getSourceContext());
            }
        }

        if ($message === '') {
            DeprecatedInputSyntaxError::raise('The "message" option must not be empty.', $token->getLine(), $stream->getSourceContext());
        }

        return new DeprecatedInputNode(
            $path,
            $removedIn,
            \is_string($replacedBy) ? $replacedBy : null,
            \is_string($message) ? $message : null,
            $token->getLine(),
        );
    }

    public function getTag(): string
    {
        return 'sw_deprecated';
    }
}
