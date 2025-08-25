<?php declare(strict_types=1);

namespace Shopware\Administration\Login;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\String\ByteString;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
final class StateValidator
{
    final public const SESSION_KEY = 'sw_sso_session_key';

    private const RANDOM_LENGTH = 64;

    public function validateRequest(Request $request): void
    {
        $this->validateState(
            $request->get('rdm'),
            $request->getSession()->get(self::SESSION_KEY),
        );

        $request->request->set('grant_type', 'shopware_grant');
        $request->request->set('code', $request->get('code'));
    }

    public function createRandom(Request $request): string
    {
        $random = ByteString::fromRandom(self::RANDOM_LENGTH)->toString();

        $request->getSession()->set(self::SESSION_KEY, $random);

        return $random;
    }

    private function validateState(?string $state, ?string $storedState): void
    {
        $validator = Validation::createValidator();
        $violations = $validator->validate($storedState, [
            new NotNull(),
            new NotBlank(),
            new Length(self::RANDOM_LENGTH),
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
