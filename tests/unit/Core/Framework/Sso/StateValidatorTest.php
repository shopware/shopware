<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Sso;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\SsoException;
use Shopware\Core\Framework\Sso\StateValidator;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StateValidator::class)]
class StateValidatorTest extends TestCase
{
    public const VALID = 'O56DJMSNQ12H5SN0PCYVCSUEEMOTOOIN73P4DBHA5UDVRLMM6X9ODZMEKYYJV6VJ';
    public const VALID_DIFFERENT = 'ICFMGIJQFXXNMTAY0974B3A7RUV657XW7L0KSZL7KT9OJIJM3TNE4WVAHRB3IPX5';
    public const INVALID_LENGTH = 'VKO9DHR0JBX8HE9ZJH07R6J3MR0Z779XGI4SK6B4D1TKSYHTOVLFBKGZGJ5HIHJ8Z2CKZD2MB6VYAQXYGNP2ORX3RZ9P4XK52HC';

    #[DataProvider('validateTestDataProvider')]
    public function testValidate(?string $state, ?string $storedState, bool $expectException): void
    {
        $validator = new StateValidator();

        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with(StateValidator::SESSION_KEY)->willReturn($storedState);

        $code = Uuid::randomHex();

        $request = new Request(['rdm' => $state, 'code' => $code]);
        $request->setSession($session);

        if ($expectException) {
            $this->expectExceptionObject(new SsoException(0, '0', 'Invalid login state'));
        }

        $validator->validateRequest($request);

        if ($expectException) {
            return;
        }

        static::assertSame('shopware_grant', $request->request->get('grant_type'));
        static::assertSame($code, $request->request->get('code'));
    }

    public function testValidateRemovesSessionKeyAfterSuccess(): void
    {
        $validator = new StateValidator();

        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with(StateValidator::SESSION_KEY)->willReturn(self::VALID);
        $session->expects($this->once())->method('remove')->with(StateValidator::SESSION_KEY);

        $request = new Request(['rdm' => self::VALID, 'code' => Uuid::randomHex()]);
        $request->setSession($session);

        $validator->validateRequest($request);
    }

    public function testCreateRandomReusesExistingKey(): void
    {
        $validator = new StateValidator();

        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with(StateValidator::SESSION_KEY)->willReturn(self::VALID);
        $session->expects($this->never())->method('set');

        $request = new Request();
        $request->setSession($session);

        $result = $validator->createRandom($request);

        static::assertSame(self::VALID, $result);
    }

    public function testCreateRandomGeneratesNewKeyWhenNoneExists(): void
    {
        $validator = new StateValidator();

        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with(StateValidator::SESSION_KEY)->willReturn(null);
        $session->expects($this->once())->method('set')->with(
            StateValidator::SESSION_KEY,
            static::callback(static fn (string $value): bool => \strlen($value) === 64),
        );

        $request = new Request();
        $request->setSession($session);

        $result = $validator->createRandom($request);

        static::assertSame(64, \strlen($result));
    }

    /**
     * @return iterable<string, array{state: string|null, storedState: string|null, expectException: bool}>
     */
    public static function validateTestDataProvider(): iterable
    {
        yield 'state and storedState is null' => [
            'state' => null,
            'storedState' => null,
            'expectException' => true,
        ];
        yield 'state is empty and storedState is null' => [
            'state' => '',
            'storedState' => null,
            'expectException' => true,
        ];
        yield 'state is empty and storedState is empty' => [
            'state' => '',
            'storedState' => '',
            'expectException' => true,
        ];
        yield 'state has invalid length and storedState is set' => [
            'state' => self::INVALID_LENGTH,
            'storedState' => self::VALID,
            'expectException' => true,
        ];
        yield 'state has valid length and storedState is different' => [
            'state' => self::VALID,
            'storedState' => self::VALID_DIFFERENT,
            'expectException' => true,
        ];
        yield 'state is valid and storedState is null' => [
            'state' => self::VALID,
            'storedState' => null,
            'expectException' => true,
        ];
        yield 'state is valid and storedState equals' => [
            'state' => self::VALID,
            'storedState' => self::VALID,
            'expectException' => false,
        ];
    }
}
