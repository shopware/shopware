<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('merchant-services')]
class FaqStruct extends StoreStruct
{
    /**
     * @var string
     */
    protected $question;

    /**
     * @var string
     */
    protected $answer;

    public static function fromArray(array $data): StoreStruct
    {
        return (new self())->assign($data);
    }

    public function getQuestion(): string
    {
        return $this->question;
    }

    public function getAnswer(): string
    {
        return $this->answer;
    }
}
