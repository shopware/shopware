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

    /**
     * @var int
     */
    private $userId;

    /**
     * AccessTokenStruct constructor.
     *
     * @param string $token
     * @param \DateTime $expirationDate
     * @param int $userId
     */
    public function __construct(string $token, \DateTime $expirationDate, int $userId)
    {
        $this->token = $token;
        $this->expirationDate = $expirationDate;
        $this->userId = $userId;
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @return \DateTime
     */
    public function getExpirationDate(): \DateTime
    {
        return $this->expirationDate;
    }

    /**
     * @return int
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    public function toArray()
    {
        return [
            'token' => $this->getToken(),
            'expirationDate' => $this->getExpirationDate()->format(DATE_ATOM),
            'userId' => $this->getUserId()
        ];
    }
}
