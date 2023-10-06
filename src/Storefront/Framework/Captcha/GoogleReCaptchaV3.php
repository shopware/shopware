<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha;

use GuzzleHttp\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

#[Package('storefront')]
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

    /**
     * {@inheritdoc}
     */
    public function isValid(Request $request, array $captchaConfig): bool
    {
        if (!$request->get(self::CAPTCHA_REQUEST_PARAMETER)) {
            return false;
        }

        $captchaConfig = \func_get_args()[1] ?? [];

        $secretKey = !empty($captchaConfig['config']['secretKey']) ? $captchaConfig['config']['secretKey'] : null;

        if (!\is_string($secretKey)) {
            return false;
        }

        try {
            $response = $this->client->request('POST', self::GOOGLE_CAPTCHA_VERIFY_ENDPOINT, [
                'form_params' => [
                    'secret' => $secretKey,
                    'response' => $request->get(self::CAPTCHA_REQUEST_PARAMETER),
                    'remoteip' => $request->getClientIp(),
                ],
            ]);

            $responseRaw = $response->getBody()->getContents();
            $response = json_decode($responseRaw, true);

            $thresholdScore = !empty($captchaConfig['config']['thresholdScore']) ? (float) $captchaConfig['config']['thresholdScore'] : self::DEFAULT_THRESHOLD_SCORE;

            return $response && (bool) $response['success'] && (float) $response['score'] >= $thresholdScore;
        } catch (ClientExceptionInterface) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return self::CAPTCHA_NAME;
    }
}
