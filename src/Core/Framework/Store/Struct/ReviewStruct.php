<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Store\Exception\InvalidExtensionRatingValueException;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('merchant-services')]
class ReviewStruct extends StoreStruct
{
    final public const MAX_RATING = 5;
    final public const MIN_RATING = 1;

    /**
     * @var int
     */
    protected $extensionId;

    /**
     * @var string
     */
    protected $headline;

    /**
     * @var string
     */
    protected $authorName;

    /**
     * @var int
     */
    protected $rating;

    /**
     * @var string|null
     */
    protected $text;

    /**
     * @var \DateTimeImmutable
     */
    protected $lastChangeDate;

    /**
     * @var string
     */
    protected $version;

    /**
     * @var bool
     */
    protected $acceptGuidelines;

    /**
     * @var array
     */
    protected $replies = [];

    public static function fromArray(array $data): StoreStruct
    {
        $review = new self();
        $data['lastChangeDate'] = new \DateTimeImmutable($data['lastChangeDate']);

        $replies = [];
        foreach ($data['replies'] as $reply) {
            $replies[] = [
                'text' => $reply['text'],
                'creationDate' => new \DateTimeImmutable($reply['creationDate']),
            ];
        }

        $data['replies'] = $replies;

        return $review->assign($data);
    }

    public static function fromRequest(int $extensionId, Request $request): ReviewStruct
    {
        $acceptGuidelines = $request->request->getBoolean('tocAccepted');
        $authorName = $request->request->get('authorName');
        $headline = $request->request->get('headline');
        $text = $request->request->get('text');
        $rating = $request->request->get('rating');
        $version = $request->request->get('version');

        if (!\is_string($authorName) || $authorName === '') {
            throw RoutingException::invalidRequestParameter('authorName');
        }

        if (!\is_string($headline) || $headline === '') {
            throw RoutingException::invalidRequestParameter('headline');
        }

        if (!\is_int($rating) || !$rating) {
            throw RoutingException::invalidRequestParameter('rating');
        }

        if (self::validateRatingValue($rating)) {
            throw new InvalidExtensionRatingValueException($rating);
        }

        if (!\is_string($version) || $version === '') {
            throw RoutingException::invalidRequestParameter('version');
        }

        $data = [
            'extensionId' => $extensionId,
            'authorName' => $authorName,
            'headline' => $headline,
            'text' => $text,
            'acceptGuidelines' => $acceptGuidelines,
            'rating' => $rating,
            'version' => $version,
        ];

        return (new self())->assign($data);
    }

    public static function validateRatingValue(int $rating): bool
    {
        return $rating < self::MIN_RATING || $rating > self::MAX_RATING;
    }

    public function getHeadline(): string
    {
        return $this->headline;
    }

    public function setHeadline(string $headline): void
    {
        $this->headline = $headline;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function setRating(int $rating): void
    {
        $this->rating = $rating;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }

    public function getLastChangeDate(): \DateTimeImmutable
    {
        return $this->lastChangeDate;
    }

    public function setLastChangeDate(\DateTimeImmutable $lastChangeDate): void
    {
        $this->lastChangeDate = $lastChangeDate;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    public function getReplies(): array
    {
        return $this->replies;
    }

    public function setReplies(array $replies): void
    {
        $this->replies = $replies;
    }

    public function getExtensionId(): int
    {
        return $this->extensionId;
    }

    public function setExtensionId(int $extensionId): void
    {
        $this->extensionId = $extensionId;
    }

    public function getAuthorName(): string
    {
        return $this->authorName;
    }

    public function setAuthorName(string $authorName): void
    {
        $this->authorName = $authorName;
    }

    public function isAcceptGuidelines(): bool
    {
        return $this->acceptGuidelines;
    }

    public function setAcceptGuidelines(bool $acceptGuidelines): void
    {
        $this->acceptGuidelines = $acceptGuidelines;
    }
}
