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
     * validate() is intentionally not overridden: the default implementation dispatches
     * through the deprecated isValid(), so subclass overrides keep working until 6.8.
     *
     * @deprecated tag:v6.8.0 - reason:becomes-unused - Will be removed, use validate() instead
     */
    public function isValid(Request $request, array $captchaConfig): bool
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'validate()'));

        if (!Feature::isActive('v6.8.0.0')) {
            $this->honeypotValue = $request->request->getString(self::CAPTCHA_REQUEST_PARAMETER);

            return \count($this->validator->validate($this)) < 1;
        }

        // A present-but-null parameter counts as empty, like a browser submitting the untouched field.
        return ($request->request->get(self::CAPTCHA_REQUEST_PARAMETER) ?? '') === '';
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return self::CAPTCHA_NAME;
    }
}
