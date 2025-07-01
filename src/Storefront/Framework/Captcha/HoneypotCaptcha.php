<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Package('framework')]
class HoneypotCaptcha extends AbstractCaptcha
{
    final public const CAPTCHA_NAME = 'honeypot';
    final public const CAPTCHA_REQUEST_PARAMETER = 'shopware_surname_confirm';

    #[Blank]
    protected ?string $honeypotValue = null;

    /**
     * @internal
     */
    public function __construct(private readonly ValidatorInterface $validator)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function isValid(Request $request, array $captchaConfig): bool
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
