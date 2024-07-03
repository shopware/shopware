<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Term\Limiter;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Limiter\CharacterLimiter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[CoversClass(CharacterLimiter::class)]
class CharacterLimiterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Context $context;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    /**
     * @param list<string> $tokens
     * @param list<string> $expected
     */
    #[DataProvider('cases')]
    public function testCharacterLimiter(int $maxCharacterCount, array $tokens, array $expected): void
    {
        $this->updateProductSearchConfig($maxCharacterCount);

        $service = new CharacterLimiter($this->connection);
        $tokens = $service->limit($tokens, $this->context);

        static::assertEquals($expected, $tokens);
    }

    /**
     * @return array<array{int, list<string>, list<string>}>
     */
    public static function cases(): array
    {
        $tokens = ['Lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'sadipscing', 'elitr', 'sed', 'diam', 'nonumy', 'eirmod', 'tempor', 'invidunt', 'ut', 'labore', 'et', 'dolore', 'magna', 'aliquyam', 'erat', 'sed', 'diam', 'voluptua'];

        return [
            [
                20,
                [],
                [],
            ],
            [
                20,
                \array_slice($tokens, 0, 2),
                \array_slice($tokens, 0, 2),
            ],
            [
                20,
                $tokens,
                ['Lorem', 'ipsum', 'dolor', 'sit', 'am'],
            ],
            [
                40,
                \array_slice($tokens, 0, 6),
                \array_slice($tokens, 0, 6),
            ],
            [
                40,
                $tokens,
                ['Lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'sadipsc'],
            ],
            [
                60,
                \array_slice($tokens, 0, 10),
                \array_slice($tokens, 0, 10),
            ],
            [
                60,
                $tokens,
                ['Lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'sadipscing', 'elitr', 'sed', 'diam', 'nonum'],
            ],
            [
                80,
                \array_slice($tokens, 0, 12),
                \array_slice($tokens, 0, 12),
            ],
            [
                80,
                $tokens,
                ['Lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'sadipscing', 'elitr', 'sed', 'diam', 'nonumy', 'eirmod', 'tempor', 'invidun'],
            ],
            [
                100,
                \array_slice($tokens, 0, 18),
                \array_slice($tokens, 0, 18),
            ],
            [
                100,
                $tokens,
                ['Lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'sadipscing', 'elitr', 'sed', 'diam', 'nonumy', 'eirmod', 'tempor', 'invidunt', 'ut', 'labore', 'et', 'dolore', 'mag'],
            ],
        ];
    }

    private function updateProductSearchConfig(int $maxCharacterCount): void
    {
        $this->connection->executeStatement(
            'UPDATE `product_search_config` SET `max_character_count` = :maxCharacterCount WHERE `language_id` = UNHEX(:id)',
            [
                'maxCharacterCount' => $maxCharacterCount,
                'id' => $this->context->getLanguageId(),
            ]
        );
    }
}
