<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Struct;

class AccessTokenStruct extends Struct
{
    /**
     * @var string
     */
    private $token;

    /**
     * @var \DateTime
     */
    private $expirationDate;

    public function __construct(string $token, \DateTime $expirationDate)
    {
        $this->token = $token;
        $this->expirationDate = $expirationDate;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getExpirationDate(): \DateTime
    {
        return $this->expirationDate;
    }

    public function toArray()
    {
        return [
            'token' => $this->getToken(),
            'expirationDate' => $this->getExpirationDate()->format(DATE_ATOM),
        ];
    }
}
