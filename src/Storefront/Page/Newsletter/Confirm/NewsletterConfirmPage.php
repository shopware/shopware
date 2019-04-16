<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Newsletter\Confirm;

use Shopware\Storefront\Framework\Page\PageWithHeader;

class NewsletterConfirmPage extends PageWithHeader
{
    /**
     * @var array
     */
    protected $result;

    public function getResult(): array
    {
        return $this->result;
    }

    public function setResult(array $result): void
    {
        $this->result = $result;
    }
}
