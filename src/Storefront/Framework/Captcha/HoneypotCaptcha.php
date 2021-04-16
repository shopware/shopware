<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Captcha;

use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SystemConfig\SystemConfigService;
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

    private SystemConfigService $systemConfigService;

    public function __construct(ValidatorInterface $validator, SystemConfigService $systemConfigService)
    {
        $this->validator = $validator;
        $this->systemConfigService = $systemConfigService;
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
    public function supports(Request $request /* , array $activeCaptcha = [] */): bool
    {
        if (!Feature::isActive('FEATURE_NEXT_12455')) {
            $activeCaptchas = $this->systemConfigService->get('core.basicInformation.activeCaptchas');

            if (empty($activeCaptchas) || !\is_array($activeCaptchas)) {
                return false;
            }

            return $request->isMethod(Request::METHOD_POST)
                && \in_array(self::CAPTCHA_NAME, $activeCaptchas, true);
        }

        return parent::supports($request, \func_get_args()[1] ?? []);
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
