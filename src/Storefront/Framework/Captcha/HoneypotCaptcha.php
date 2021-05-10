<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class HoneypotCaptcha extends AbstractCaptcha
{
    public const CAPTCHA_NAME = 'honeypot';
    public const CAPTCHA_REQUEST_PARAMETER = 'shopware_surname_confirm';

    protected ?string $honeypotValue;

    private ValidatorInterface $validator;

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
    public function isValid(Request $request): bool
    {
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
