<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Newsletter\Account;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Storefront\Pagelet\Pagelet;

/**
 * @internal (flag:FEATURE_NEXT_14001) remove comment on feature release
 */
class NewsletterAccountPagelet extends Pagelet
{
    protected CustomerEntity $customer;

    protected ?bool $success = null;

    protected ?array $messages = null;

    protected ?bool $newsletterDoi = null;

    protected ?string $newsletterStatus = null;

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    public function isSuccess(): ?bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    public function getMessages(): ?array
    {
        return $this->messages;
    }

    public function setMessages(array $messages): void
    {
        $this->messages = $messages;
    }

    public function addMessages(array $messages): void
    {
        if (!\is_array($this->messages)) {
            $this->messages = $messages;
        } else {
            $this->messages = array_merge($this->messages, $messages);
        }
    }

    public function isNewsletterDoi(): ?bool
    {
        return $this->newsletterDoi;
    }

    public function setNewsletterDoi(bool $newsletterDoi): void
    {
        $this->newsletterDoi = $newsletterDoi;
    }

    public function getNewsletterStatus(): ?string
    {
        return $this->newsletterStatus;
    }

    public function setNewsletterStatus(?string $newsletterStatus): void
    {
        $this->newsletterStatus = $newsletterStatus;
    }
}
