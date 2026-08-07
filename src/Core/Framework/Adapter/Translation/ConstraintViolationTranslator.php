<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Translation;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Package('discovery')]
readonly class ConstraintViolationTranslator
{
    /**
     * @internal
     */
    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function translate(ConstraintViolationInterface $violation): string
    {
        // use custom message template if available
        $key = $violation->getMessageTemplate();
        $message = $this->translator->trans($key, $violation->getParameters());

        if ($message !== $key) {
            return $message;
        }

        // use custom error code message if available
        $key = 'error.' . $violation->getCode();
        $message = $this->translator->trans($key, $violation->getParameters());

        if ($message !== $key) {
            return $message;
        }

        // fallback to default symfony message
        return (string) $violation->getMessage();
    }
}
