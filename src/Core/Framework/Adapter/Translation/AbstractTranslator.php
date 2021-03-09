<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Translation;

use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractTranslator implements TranslatorInterface, TranslatorBagInterface, LocaleAwareInterface
{
    abstract public function getDecorated(): AbstractTranslator;

    abstract public function trace(string $key, \Closure $param);

    abstract public function getTrace(string $key): array;
}
