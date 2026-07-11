<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha;

use GuzzleHttp\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

#[Package('framework')]
class GoogleReCaptchaV2 extends AbstractCaptcha
{
    final public const CAPTCHA_NAME = 'googleReCaptchaV2';
    final public const CAPTCHA_REQUEST_PARAMETER = '_grecaptcha_v2';
    private const GOOGLE_CAPTCHA_VERIFY_ENDPOINT = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * @internal
     */
    public function __construct(private readonly ClientInterface $client)
    {
    }

    public function validate(Request $request, array $captchaConfig): ConstraintViolationList
    {
        // @deprecated tag:v6.8.0 - inline the isValid()/getViolations() logic here when the deprecated methods are removed
        if (Feature::silent('v6.8.0.0', fn (): bool => $this->isValid($request, $captchaConfig))) {
            return new ConstraintViolationList();
        }

        $violations = Feature::silent('v6.8.0.0', fn (): ConstraintViolationList => $this->getViolations());
        if ($violations->count() === 0) {
            $violations->add($this->createViolation(
                $request->request->get(self::CAPTCHA_REQUEST_PARAMETER)
                    // Token present but not verifiable: generic error instead of a 403.
                    ? CaptchaException::INVALID_CAPTCHA_ERROR
                    // No token: recoverable for a real customer (cookies not accepted yet).
                    : CaptchaException::RECAPTCHA_COOKIE_REQUIRED_VIOLATION
            ));
        }

        return $violations;
    }

    /**
     * @deprecated tag:v6.8.0 - reason:becomes-unused - Will be removed, use validate() instead
     */
    public function isValid(Request $request, array $captchaConfig): bool
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'validate()'));

        if (!$request->request->get(self::CAPTCHA_REQUEST_PARAMETER)) {
            return false;
        }

        return $this->verify($request, $captchaConfig);
    }

    /**
     * reCAPTCHA failures always carry customer-facing violations, so they are rendered
     * as form errors instead of breaking the request.
     */
    public function shouldBreak(): bool
    {
        return false;
    }

    public function getName(): string
    {
        return self::CAPTCHA_NAME;
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
