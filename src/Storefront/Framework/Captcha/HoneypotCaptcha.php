<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Feature;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @package storefront
 */
#[Package('storefront')]
class HoneypotCaptcha extends AbstractCaptcha
{
    public const CAPTCHA_NAME = 'honeypot';
    public const CAPTCHA_REQUEST_PARAMETER = 'shopware_surname_confirm';

    protected ?string $honeypotValue;

    private ValidatorInterface $validator;

    /**
     * @internal
     */
    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Default method for determining constraints when using the Symfony validator.
     */
    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint('honeypotValue', new Blank());
    }

    /**
     * {@inheritdoc}
     */
    public function isValid(Request $request/* , array $captchaConfig */): bool
    {
        if (\func_num_args() < 2 || !\is_array(func_get_arg(1))) {
            Feature::triggerDeprecationOrThrow(
                'v6.5.0.0',
                'Method `isValid()` in `HoneypotCaptcha` expects passing the `$captchaConfig` as array as the second parameter in v6.5.0.0.'
            );
        }

        $this->honeypotValue = $request->get(self::CAPTCHA_REQUEST_PARAMETER, '');

        return \count($this->validator->validate($this)) < 1;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return self::CAPTCHA_NAME;
    }
}
