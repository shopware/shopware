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
    final public const SESSION_KEY = 'sw_sso_session_key';

    public function validateRequest(Request $request): void
    {
        $this->validateState(
            $request->get('rdm'),
            $request->getSession()->get(self::SESSION_KEY),
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
