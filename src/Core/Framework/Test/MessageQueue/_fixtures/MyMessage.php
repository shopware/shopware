<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue\_fixtures;

use Shopware\Core\Framework\MessageQueue\Message;

class MyMessage extends Message
{
    /**
     * @var string
     */
    private $myProp;

    public function getMyProp(): string
    {
        return $this->myProp;
    }

    public function setMyProp(string $myProp): void
    {
        $this->myProp = $myProp;
    }
}
