<?php

namespace Shopware\Core\Profiling\Twig;

use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

class ProfileTokenParser extends AbstractTokenParser
{
    public function parse(Token $token)
    {
        $stream = $this->parser->getStream();

        $profileName = $stream->expect(Token::NAME_TYPE)->getValue();

        $stream->expect(Token::BLOCK_END_TYPE);

        $body = $this->parser->subparse([$this, 'decideProfileEnd'], true);

        $stream->expect(Token::BLOCK_END_TYPE);

        return new ProfileNode($profileName, $body, $token->getLine(), $this->getTag());
    }

    public function decideProfileEnd(Token $token)
    {
        return $token->test('endprofile');
    }

    public function getTag()
    {
        return 'profile';
    }
}
