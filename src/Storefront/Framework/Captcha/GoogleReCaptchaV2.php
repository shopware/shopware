<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha;

use GuzzleHttp\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

#[Package('framework')]
class GoogleReCaptchaV2 extends AbstractCaptcha
{
    final public const CAPTCHA_NAME = 'googleReCaptchaV2';
    final public const CAPTCHA_REQUEST_PARAMETER = '_grecaptcha_v2';

    // Shown when no token was submitted, usually because the technically required cookies
    // were not accepted. Maps to an `error.*` snippet explaining how to recover.
    final public const COOKIE_REQUIRED_VIOLATION = 'VIOLATION::RECAPTCHA_COOKIE_REQUIRED';

    private const GOOGLE_CAPTCHA_VERIFY_ENDPOINT = 'https://www.google.com/recaptcha/api/siteverify';

    private ConstraintViolationList $violations;

    /**
     * @internal
     */
    public function __construct(private readonly ClientInterface $client)
    {
        $this->violations = new ConstraintViolationList();
    }

    public function isValid(Request $request, array $captchaConfig): bool
    {
        $this->violations = new ConstraintViolationList();

        if (!$request->request->get(self::CAPTCHA_REQUEST_PARAMETER)) {
            // No token: recoverable for a real customer (cookies not accepted yet).
            $this->violations->add($this->createViolation(self::COOKIE_REQUIRED_VIOLATION));

            return false;
        }

        if ($this->verify($request, $captchaConfig)) {
            return true;
        }

        // Token present but not verifiable: surface a generic error instead of a 403.
        $this->violations->add($this->createViolation(CaptchaException::INVALID_CAPTCHA_ERROR));

        return false;
    }

    public function getName(): string
    {
        return self::CAPTCHA_NAME;
    }

    public function getViolations(): ConstraintViolationList
    {
        return $this->violations;
    }

    /**
     * @param array<string, mixed> $captchaConfig
     */
    private function verify(Request $request, array $captchaConfig): bool
    {
        $secretKey = $captchaConfig['config']['secretKey'] ?? null;
        if (!\is_string($secretKey) || $secretKey === '') {
            return false;
        }

        try {
            $response = $this->client->request('POST', self::GOOGLE_CAPTCHA_VERIFY_ENDPOINT, [
                'form_params' => [
                    'secret' => $secretKey,
                    'response' => $request->request->get(self::CAPTCHA_REQUEST_PARAMETER),
                    'remoteip' => $request->getClientIp(),
                ],
            ]);

            $responseRaw = $response->getBody()->getContents();
            try {
                $response = json_decode($responseRaw, true, flags: \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $response = [];
            }

            return \is_array($response)
                && $response !== []
                && $response['success'];
        } catch (ClientExceptionInterface) {
            return false;
        }
    }

    private function createViolation(string $code): ConstraintViolation
    {
        return new ConstraintViolation('', '', [], '', '', '', null, $code);
    }
}
