<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Service;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\DataBag;

#[Package('checkout')]
class EmailIdnConverter
{
    public static function decode(string $email): string
    {
        $parts = explode('@', $email);

        if (\count($parts) < 2 || !str_starts_with($parts[1], 'xn--')) {
            return $email;
        }

        return \sprintf('%s@%s', $parts[0], idn_to_utf8($parts[1]));
    }

    public static function encode(string $email): string
    {
        $parts = explode('@', $email);

        if (\count($parts) !== 2 || mb_check_encoding($email, 'ASCII')) {
            return $email;
        }

        return \sprintf('%s@%s', $parts[0], idn_to_ascii($parts[1]));
    }

    public static function encodeDataBag(DataBag $data, string $name = 'email'): void
    {
        $email = $data->get($name);
        if (\is_string($email)) {
            $data->set($name, EmailIdnConverter::encode($email));
        }
    }

    public static function decodeDataBag(DataBag $data, string $name = 'email'): void
    {
        $email = $data->get($name);
        if (\is_string($email)) {
            $data->set($name, EmailIdnConverter::encode($email));
        }
    }
}
