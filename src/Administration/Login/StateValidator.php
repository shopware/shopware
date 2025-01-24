<?php declare(strict_types=1);

namespace Shopware\Administration\Login;

use Shopware\Administration\Login\Config\LoginConfig;
use Shopware\Administration\Login\Exception\LoginException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('after-sales')]
final class StateValidator
{
    public function __construct(
        private readonly LoginConfig $loginConfig,
    ) {}

    public function validateRequest(Request $request): void
    {
        $this->validateState(
            $request->get('rdm'),
            $request->getSession()->get($this->loginConfig->getSessionKey())
        );

        $request->request->set('grant_type', 'shopware_grant');
        $request->request->set('code', $request->get('code'));
    }

    private function validateState(?string $state, ?string $storedState): void
    {
        $validator = Validation::createValidator();
        $violations = $validator->validate($storedState, [
            new NotNull(),
            new NotBlank(),
            new Length(LoginConfig::RANDOM_LENGTH),
        ]);

        if ($violations->count() > 0) {
            throw LoginException::invalidLoginState();
        }

        $violations = $validator->validate($state, new EqualTo($storedState));
        if ($violations->count() > 0) {
            throw LoginException::invalidLoginState();
        }
    }
}
