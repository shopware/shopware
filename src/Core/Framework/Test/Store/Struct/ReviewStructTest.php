<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Struct;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Store\Struct\ReviewStruct;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ReviewStructTest extends TestCase
{
    public function testFromRequest(): void
    {
        $request = new Request([], [
            'authorName' => 'Author',
            'headline' => 'Headline',
            'text' => 'Text',
            'tocAccepted' => true,
            'rating' => 3,
            'version' => '1.1.0',
        ]);

        $rating = ReviewStruct::fromRequest(1, $request);

        static::assertEquals(1, $rating->getExtensionId());
        static::assertEquals('Author', $rating->getAuthorName());
        static::assertEquals('Headline', $rating->getHeadline());
        static::assertEquals('Text', $rating->getText());
        static::assertTrue($rating->isAcceptGuidelines());
        static::assertEquals(3, $rating->getRating());
        static::assertEquals('1.1.0', $rating->getVersion());
    }

    public function testFromRequestThrowsIfAuthorNameIsInvalid(): void
    {
        $request = new Request([], [
            'tocAccepted' => true,
        ]);

        static::expectException(RoutingException::class);
        static::expectExceptionMessage('The parameter "authorName" is invalid.');
        ReviewStruct::fromRequest(1, $request);
    }

    public function testFromRequestThrowsIfHeadlineIsInvalid(): void
    {
        $request = new Request([], [
            'authorName' => 'Author',
            'tocAccepted' => true,
        ]);

        static::expectException(RoutingException::class);
        static::expectExceptionMessage('The parameter "headline" is invalid.');
        ReviewStruct::fromRequest(1, $request);
    }

    public function testFromRequestThrowsIfRatingIsInvalid(): void
    {
        $request = new Request([], [
            'authorName' => 'Author',
            'headline' => 'Headline',
            'text' => 'Text',
            'tocAccepted' => true,
        ]);

        static::expectException(RoutingException::class);
        static::expectExceptionMessage('The parameter "rating" is invalid.');
        ReviewStruct::fromRequest(1, $request);
    }
}
