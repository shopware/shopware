<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Limiter;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('core')]
class CharacterLimiter extends AbstractCharacterLimiter
{
    private const DEFAULT_MAX_CHARACTER_COUNT = 60;

    /**
     * @var array<string, int>
     */
    private array $maxCharacterCount = [];

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getDecorated(): AbstractCharacterLimiter
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * {@inheritdoc}
     */
    public function limit(array $tokens, Context $context): array
    {
        if (empty($tokens)) {
            return $tokens;
        }

        $maxCharacterCount = $this->getMaxCharacterCount($context->getLanguageId());

        if (mb_strlen(implode('', $tokens)) <= $maxCharacterCount) {
            return $tokens;
        }

        $word = '';
        $availableCount = $maxCharacterCount;

        foreach ($tokens as $token) {
            if (mb_strlen($token) > $availableCount) {
                $word .= mb_substr($token, 0, $availableCount);

                break;
            }

            $word .= $token . ' ';
            $availableCount -= mb_strlen($token);
        }

        $tokens = explode(' ', trim($word));

        return $tokens;
    }

    public function reset(): void
    {
        $this->maxCharacterCount = [];
    }

    private function getMaxCharacterCount(string $languageId): int
    {
        if (isset($this->maxCharacterCount[$languageId])) {
            return $this->maxCharacterCount[$languageId];
        }

        $maxCharacterCount = $this->connection->fetchOne('
            SELECT `max_character_count`
            FROM product_search_config
            WHERE language_id = :languageId
            LIMIT 1
        ', ['languageId' => Uuid::fromHexToBytes($languageId)]);

        return $this->maxCharacterCount[$languageId] = (int) ($maxCharacterCount ?? self::DEFAULT_MAX_CHARACTER_COUNT);
    }
}
