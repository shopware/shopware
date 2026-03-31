<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha;

use GuzzleHttp\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
class GoogleReCaptchaV3 extends AbstractCaptcha
{
    final public const CAPTCHA_NAME = 'googleReCaptchaV3';
    final public const CAPTCHA_REQUEST_PARAMETER = '_grecaptcha_v3';
    private const GOOGLE_CAPTCHA_VERIFY_ENDPOINT = 'https://www.google.com/recaptcha/api/siteverify';
    private const DEFAULT_THRESHOLD_SCORE = 0.5;

    /**
     * @internal
     */
    public function __construct(private readonly ClientInterface $client)
    {
    }

    public function isValid(Request $request, array $captchaConfig): bool
    {
        if (!$request->request->get(self::CAPTCHA_REQUEST_PARAMETER)) {
            return false;
        }

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

            $thresholdScore = (float) ($captchaConfig['config']['thresholdScore'] ?? self::DEFAULT_THRESHOLD_SCORE);
            if ($thresholdScore === 0.0) {
                $thresholdScore = self::DEFAULT_THRESHOLD_SCORE;
            }

            return \is_array($response)
                && $response !== []
                && $response['success']
                && ((float) $response['score']) >= $thresholdScore;
        } catch (ClientExceptionInterface) {
            return false;
        }
    }

    public function getName(): string
    {
        return self::CAPTCHA_NAME;
    }
}
