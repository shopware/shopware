<?php declare(strict_types=1);

namespace Shopware\Recovery\Install\Struct;

class AdminUser extends Struct
{
    /**
     * @var string
     */
    public $username;

    /**
     * @var string
     */
    public $password;

    /**
     * @var string
     */
    public $email;

    /**
     * @var string
     */
    public $locale;

    /**
     * @var string
     */
    public $firstName;

    /**
     * @var string
     */
    public $lastName;
}
