<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\MailException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(MailException::class)]
class MailExceptionTest extends TestCase
{
    public function testItThrowsException(): void
    {
        $testCases = [
            [
                'exception' => MailException::givenMailAgentIsInvalid('john'),
                'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'errorCode' => 'MAIL__GIVEN_AGENT_INVALID',
                'message' => 'Invalid mail agent given "john"',
            ],
            [
                'exception' => MailException::givenSendMailOptionIsInvalid('blah', ['foo', 'bar']),
                'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'errorCode' => 'MAIL__GIVEN_OPTION_INVALID',
                'message' => 'Given sendmail option "blah" is invalid. Available options: foo, bar',
            ],
            [
                'exception' => MailException::mailBodyTooLong(5),
                'statusCode' => Response::HTTP_BAD_REQUEST,
                'errorCode' => 'MAIL__MAIL_BODY_TOO_LONG',
                'message' => 'Mail body is too long. Maximum allowed length is 5',
            ],
        ];

        foreach ($testCases as $testCase) {
            $this->runTestCase(
                $testCase['exception'],
                $testCase['statusCode'],
                $testCase['errorCode'],
                $testCase['message']
            );
        }
    }

    private function runTestCase(ShopwareHttpException $exception, int $statusCode, string $errorCode, string $message): void
    {
        static::assertEquals($statusCode, $exception->getStatusCode());
        static::assertEquals($errorCode, $exception->getErrorCode());
        static::assertEquals($message, $exception->getMessage());
    }
}
