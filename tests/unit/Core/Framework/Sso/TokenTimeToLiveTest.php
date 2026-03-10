<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Sso;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\SsoException;
use Shopware\Core\Framework\Sso\TokenTimeToLive;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TokenTimeToLive::class)]
class TokenTimeToLiveTest extends TestCase
{
    public const TTL_SIXTY_SECONDS = 'PT60S';
    public const TTL_TEN_SECONDS = 'PT10S';

    #[DataProvider('dateIntervals')]
    public function testGetLowerTTL(\DateInterval $one, \DateInterval $two, \DateInterval $expected): void
    {
        $result = TokenTimeToLive::getLowerTTL($one, $two);

        static::assertEquals($expected, $result);
    }

    /**
     * @return array<string, array<string, \DateInterval>>
     */
    public static function dateIntervals(): array
    {
        return [
            'one is smaller' => [
                'one' => new \DateInterval(self::TTL_TEN_SECONDS),
                'two' => new \DateInterval(self::TTL_SIXTY_SECONDS),
                'expected' => new \DateInterval(self::TTL_TEN_SECONDS),
            ],

            'two is smaller' => [
                'one' => new \DateInterval(self::TTL_SIXTY_SECONDS),
                'two' => new \DateInterval(self::TTL_TEN_SECONDS),
                'expected' => new \DateInterval(self::TTL_TEN_SECONDS),
            ],

            'one and two equals' => [
                'one' => new \DateInterval(self::TTL_SIXTY_SECONDS),
                'two' => new \DateInterval(self::TTL_SIXTY_SECONDS),
                'expected' => new \DateInterval(self::TTL_SIXTY_SECONDS),
            ],

            'one is negative' => [
                'one' => self::createDateIntervalInPast(self::TTL_SIXTY_SECONDS),
                'two' => new \DateInterval(self::TTL_SIXTY_SECONDS),
                'expected' => new \DateInterval(self::TTL_SIXTY_SECONDS),
            ],

            'two is negative' => [
                'one' => new \DateInterval(self::TTL_SIXTY_SECONDS),
                'two' => self::createDateIntervalInPast(self::TTL_SIXTY_SECONDS),
                'expected' => new \DateInterval(self::TTL_SIXTY_SECONDS),
            ],
        ];
    }

    public function testGetLowerTTLShouldThrowException(): void
    {
        $one = self::createDateIntervalInPast(self::TTL_SIXTY_SECONDS);
        $two = self::createDateIntervalInPast(self::TTL_TEN_SECONDS);

        $this->expectExceptionObject(SsoException::negativeTimeToLive());

        TokenTimeToLive::getLowerTTL($one, $two);
    }

    private static function createDateIntervalInPast(string $intervalString): \DateInterval
    {
        return (new \DateTime())->diff((new \DateTime())->sub(new \DateInterval($intervalString)));
    }
}
