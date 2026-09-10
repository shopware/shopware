<?php declare(strict_types=1);

namespace Shopware\Core\System\OAuthClient\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ListFieldSerializer;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @internal
 */
#[Package('framework')]
class RedirectUriListFieldSerializer extends ListFieldSerializer
{
    protected function getConstraints(Field $field): array
    {
        return [new Sequentially([
            new NotNull(),
            new Type('list'),
            new Count(min: 1, max: 20),
            new All([new Sequentially([
                new Type('string'),
                new NotBlank(),
                new Length(max: 2048),
                new Callback($this->validateRedirectUri(...)),
            ])]),
        ])];
    }

    private function validateRedirectUri(string $uri, ExecutionContextInterface $context): void
    {
        if ($this->isValidRedirectUri($uri)) {
            return;
        }

        $context->buildViolation('Use HTTPS, or HTTP with 127.0.0.1 or [::1], without credentials, fragments or wildcards.')
            ->addViolation();
    }

    private function isValidRedirectUri(string $uri): bool
    {
        if (!filter_var($uri, \FILTER_VALIDATE_URL) || preg_match('/[\s*\\\\]/', $uri)) {
            return false;
        }

        $parts = parse_url($uri);
        if ($parts === false || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return false;
        }

        return ($parts['scheme'] ?? '') === 'https'
            || (($parts['scheme'] ?? '') === 'http' && \in_array($parts['host'] ?? '', ['127.0.0.1', '[::1]'], true));
    }
}
