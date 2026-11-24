<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\PasswordHasher;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\PasswordHasher\NativePasswordHasher;
use Shopware\Core\Framework\PasswordHasher\PasswordHasherException;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NativePasswordHasher::class)]
class NativePasswordHasherTest extends TestCase
{
    public function testDefaultAlgorithmMatchesPhpDefault(): void
    {
        $hash = (new NativePasswordHasher())->hash('shopware');

        static::assertSame(\PASSWORD_DEFAULT, password_get_info($hash)['algo']);
        static::assertTrue(password_verify('shopware', $hash));
    }

    public function testHashUsesConfiguredOptions(): void
    {
        $hash = (new NativePasswordHasher(NativePasswordHasher::ALGORITHM_BCRYPT, ['cost' => 5]))->hash('shopware');

        static::assertStringStartsWith('$2y$05$', $hash);
    }

    public function testVerify(): void
    {
        $hasher = new NativePasswordHasher();
        $hash = $hasher->hash('shopware');

        static::assertTrue($hasher->verify($hash, 'shopware'));
        static::assertFalse($hasher->verify($hash, 'wrong'));
    }

    public function testVerifiesHashesOfOtherNativeAlgorithmsAndMarksThemForRehash(): void
    {
        if (!\defined('PASSWORD_ARGON2ID')) {
            static::markTestSkipped('PHP is compiled without Argon2 support');
        }

        $bcryptHash = password_hash('shopware', \PASSWORD_BCRYPT, ['cost' => 5]);
        $hasher = new NativePasswordHasher(NativePasswordHasher::ALGORITHM_ARGON2ID);

        static::assertTrue($hasher->verify($bcryptHash, 'shopware'));
        static::assertTrue($hasher->needsRehash($bcryptHash));
        static::assertFalse($hasher->needsRehash($hasher->hash('shopware')));
    }

    public function testVerifyPlaceholderVerifiesTheGivenPlaceholderHash(): void
    {
        // The invalid cost would make hash() throw, so passing proves that only the placeholder hash is verified
        $hasher = new NativePasswordHasher(
            NativePasswordHasher::ALGORITHM_BCRYPT,
            ['cost' => 99],
            password_hash('placeholder', \PASSWORD_BCRYPT, ['cost' => 4]),
        );

        $hasher->verifyPlaceholder('shopware');

        $this->expectException(\ValueError::class);
        $hasher->hash('shopware');
    }

    public function testVerifyPlaceholderHashesWithoutPlaceholderHash(): void
    {
        $hasher = new NativePasswordHasher(NativePasswordHasher::ALGORITHM_BCRYPT, ['cost' => 99]);

        $this->expectException(\ValueError::class);
        $hasher->verifyPlaceholder('shopware');
    }

    public function testNeedsRehashWhenOptionsChanged(): void
    {
        $hash = password_hash('shopware', \PASSWORD_BCRYPT, ['cost' => 5]);

        static::assertFalse((new NativePasswordHasher(NativePasswordHasher::ALGORITHM_BCRYPT, ['cost' => 5]))->needsRehash($hash));
        static::assertTrue((new NativePasswordHasher(NativePasswordHasher::ALGORITHM_BCRYPT, ['cost' => 6]))->needsRehash($hash));
    }

    #[DataProvider('isHashedProvider')]
    public function testIsHashed(string $value, bool $expected): void
    {
        static::assertSame($expected, (new NativePasswordHasher())->isHashed($value));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function isHashedProvider(): iterable
    {
        yield 'bcrypt hash is recognized' => [password_hash('shopware', \PASSWORD_BCRYPT, ['cost' => 4]), true];
        yield 'plain text password is not a hash' => ['shopware', false];
        yield 'md5 digest of legacy passwords is not a native hash' => [md5('shopware'), false];
    }

    public function testThrowsForUnsupportedAlgorithm(): void
    {
        $this->expectExceptionObject(PasswordHasherException::unsupportedAlgorithm('md5', password_algos()));

        new NativePasswordHasher('md5');
    }

    public function testGetDecoratedThrows(): void
    {
        $this->expectException(DecorationPatternException::class);

        (new NativePasswordHasher())->getDecorated();
    }
}
