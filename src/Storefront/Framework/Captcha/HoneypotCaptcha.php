<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Package('discovery')]
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

    public function validate(Request $request, array $captchaConfig): ConstraintViolationList
    {
        $violations = new ConstraintViolationList();

        if (!$this->isHoneypotEmpty($request)) {
            // A filled honeypot is bot-only, so there is no customer-facing recovery hint.
            $violations->add(new ConstraintViolation('', '', [], '', '', '', null, CaptchaException::INVALID_CAPTCHA_ERROR));
        }

        return $violations;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return self::CAPTCHA_NAME;
    }

    private function isHoneypotEmpty(Request $request): bool
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $this->honeypotValue = $request->request->getString(self::CAPTCHA_REQUEST_PARAMETER);

            return \count($this->validator->validate($this)) < 1;
        }

        // A present-but-null parameter counts as empty, like a browser submitting the untouched field.
        return ($request->request->get(self::CAPTCHA_REQUEST_PARAMETER) ?? '') === '';
    }
}
