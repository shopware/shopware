<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\TokenParser;

use Shopware\Core\Framework\Adapter\Twig\Exception\DeprecatedSyntaxError;
use Shopware\Core\Framework\Adapter\Twig\Node\DeprecatedAliasNode;
use Shopware\Core\Framework\Adapter\Twig\Node\DeprecatedInputNode;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * @internal
 */
#[Package('framework')]
final class DeprecatedTokenParser extends AbstractTokenParser
{
    private const OPTIONS = ['replaced_by', 'message', 'removed_in'];
    private const ROOT_PATH_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/D';
    private const INPUT_PATH_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/D';

    public function parse(Token $token): Node
    {
        $stream = $this->parser->getStream();
        $kindToken = $stream->expect(Token::NAME_TYPE);
        $kind = $kindToken->getValue();

        if (!\in_array($kind, ['input', 'alias'], true)) {
            DeprecatedSyntaxError::raise('The "sw_deprecated" tag supports only input and alias declarations.', $kindToken->getLine(), $stream->getSourceContext());
        }

        $pathToken = $stream->expect(Token::STRING_TYPE);
        $path = $pathToken->getValue();
        $pathPattern = $kind === 'alias' ? self::ROOT_PATH_PATTERN : self::INPUT_PATH_PATTERN;
        if (!\is_string($path) || !\preg_match($pathPattern, $path)) {
            $expectedPath = $kind === 'alias' ? 'root variable' : 'root or dotted path';
            DeprecatedSyntaxError::raise(\sprintf('A deprecated %s must be a literal %s.', $kind, $expectedPath), $pathToken->getLine(), $stream->getSourceContext());
        }

        $options = [];
        while (!$stream->test(Token::BLOCK_END_TYPE)) {
            $name = $stream->expect(Token::NAME_TYPE);
            $option = $name->getValue();
            if (!\is_string($option) || !\in_array($option, self::OPTIONS, true)) {
                DeprecatedSyntaxError::raise(\sprintf('Unknown option "%s" for the "sw_deprecated" tag.', (string) $option), $name->getLine(), $stream->getSourceContext());
            }

            if (\array_key_exists($option, $options)) {
                DeprecatedSyntaxError::raise(\sprintf('The option "%s" is declared more than once.', $option), $name->getLine(), $stream->getSourceContext());
            }

            $stream->expect(Token::OPERATOR_TYPE, '=');
            $value = $stream->expect(Token::STRING_TYPE);
            $options[$option] = $value->getValue();
        }

        $stream->expect(Token::BLOCK_END_TYPE);

        $removedIn = $options['removed_in'] ?? null;
        if (!\is_string($removedIn)) {
            DeprecatedSyntaxError::raise(\sprintf('A deprecated %s requires the "removed_in" option.', $kind), $token->getLine(), $stream->getSourceContext());
        }

        $feature = Feature::getRegisteredFeatures()[Feature::normalizeName($removedIn)] ?? null;
        if (!\preg_match('/^v\d+\.\d+\.\d+\.\d+$/D', $removedIn) || $feature === null || !($feature['major'] ?? false)) {
            DeprecatedSyntaxError::raise(\sprintf('The "removed_in" option must name a canonical registered major feature flag, got "%s".', $removedIn), $token->getLine(), $stream->getSourceContext());
        }

        $replacedBy = $options['replaced_by'] ?? null;
        $message = $options['message'] ?? null;

        if ($kind === 'alias') {
            if (!\is_string($replacedBy) || $message !== null) {
                DeprecatedSyntaxError::raise('A deprecated alias requires "replaced_by" and does not support "message".', $token->getLine(), $stream->getSourceContext());
            }

            if (!\preg_match(self::ROOT_PATH_PATTERN, $replacedBy)) {
                DeprecatedSyntaxError::raise('The "replaced_by" option of an alias must be a literal root variable.', $token->getLine(), $stream->getSourceContext());
            }

            if ($replacedBy === $path) {
                DeprecatedSyntaxError::raise('The replacement must differ from the deprecated alias.', $token->getLine(), $stream->getSourceContext());
            }

            return new DeprecatedAliasNode($path, $removedIn, $replacedBy, $token->getLine());
        }

        if (\is_string($replacedBy) === \is_string($message)) {
            DeprecatedSyntaxError::raise('A deprecated input requires exactly one of "replaced_by" or "message".', $token->getLine(), $stream->getSourceContext());
        }

        if (\is_string($replacedBy)) {
            if (!\preg_match(self::INPUT_PATH_PATTERN, $replacedBy)) {
                DeprecatedSyntaxError::raise('The "replaced_by" option must be a literal root or dotted path.', $token->getLine(), $stream->getSourceContext());
            }

            if ($replacedBy === $path) {
                DeprecatedSyntaxError::raise('The replacement must differ from the deprecated input path.', $token->getLine(), $stream->getSourceContext());
            }
        }

        if ($message === '') {
            DeprecatedSyntaxError::raise('The "message" option must not be empty.', $token->getLine(), $stream->getSourceContext());
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
