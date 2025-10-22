<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Package('framework')]
class HoneypotCaptcha extends AbstractCaptcha
{
    final public const CAPTCHA_NAME = 'honeypot';
    final public const CAPTCHA_REQUEST_PARAMETER = 'shopware_surname_confirm';

    /**
     * @deprecated tag:v6.8.0 - Will be removed, as the Symfony validator is not used anymore to validate the honeypot captcha
     */
    protected ?string $honeypotValue = null;

    /**
     * @internal
     */
    public function __construct(private readonly ValidatorInterface $validator)
    {
    }

    /**
     * Default method for determining constraints when using the Symfony validator.
     *
     * @deprecated tag:v6.8.0 - Will be removed, as the Symfony validator is not used anymore to validate the honeypot captcha
     */
    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0'));

        $metadata->addPropertyConstraint('honeypotValue', new Blank());
    }

    /**
     * {@inheritdoc}
     */
    public function isValid(Request $request, array $captchaConfig): bool
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $this->honeypotValue = $request->get(self::CAPTCHA_REQUEST_PARAMETER, '');

            return \count($this->validator->validate($this)) < 1;
        }

        return $request->get(self::CAPTCHA_REQUEST_PARAMETER, '') === '';
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return self::CAPTCHA_NAME;
    }
}
