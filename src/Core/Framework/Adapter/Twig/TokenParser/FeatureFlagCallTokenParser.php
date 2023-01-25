<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\TokenParser;

use Shopware\Core\Framework\Adapter\Twig\Node\FeatureCallSilentToken;
use Shopware\Core\Framework\Log\Package;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

#[Package('core')]
class FeatureFlagCallTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): FeatureCallSilentToken
    {
        $stream = $this->parser->getStream();

        // Parse the string field inside
        $flagToken = $stream->expect(Token::STRING_TYPE);
        $flagName = $flagToken->getValue();

        // The feature flag is followed by an endblock token, remove it from the stream
        $stream->next();

        // Parse the body of the tag inside
        $body = $this->parser->subparse($this->decideBlockEnd(...), true);

        // We read until the string of the end of the block. But we need to parse the end tag as well, so the parser is on clean state again.
        $stream->next();

        return new FeatureCallSilentToken($flagName, $body, $flagToken->getLine(), $this->getTag());
    }

    public function getTag(): string
    {
        return 'sw_silent_feature_call';
    }

    public function decideBlockEnd(Token $token): bool
    {
        return $token->test('endsw_silent_feature_call');
    }
}
