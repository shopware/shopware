---
title: Minify flow storer
issue: NEXT-25364
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
---
# Core
* Added new `ScalarValuesAware` and `ScalarValuesStorer` class, which allows to store scalar values for flows.
* Deprecated diverse flow storer classes and interfaces in favor of the new `ScalarValuesStorer` class:
  * `ConfirmUrlAware`
  * `ContactFormDataAware`
  * `ContentsAware`
  * `ContextTokenAware`
  * `DataAware`
  * `EmailAware`
  * `MediaUploadedAware`
  * `NameAware`
  * `RecipientsAware`
  * `ResetUrlAware`
  * `ReviewFormDataAware`
  * `ScalarStoreAware`
  * `ShopNameAware`
  * `SubjectAware`
  * `TemplateDataAware`
  * `UrlAware`
  * `ConfirmUrlStorer`
  * `ContactFormDataStorer`
  * `ContentsStorer`
  * `ContextTokenStorer`
  * `DataStorer`
  * `EmailStorer`
  * `NameStorer`
  * `RecipientsStorer`
  * `ResetUrlStorer`
  * `ReviewFormDataStorer`
  * `ScalarFlowStorer`
  * `ScalarValuesStorer`
  * `ShopNameStorer`
  * `SubjectStorer`
  * `TemplateDataStorer`
  * `UrlStorer`
* Replaced deprecated flow storer interfaces in the flow event with the new `ScalarValuesAware` interface in the following events:
  * `CustomerAccountRecoverRequestEvent`
  * `CustomerBeforeLoginEvent`
  * `CustomerDoubleOptInRegistrationEvent`
  * `CustomerLoginEvent`
  * `DoubleOptInGuestOrderEvent`
  * `ContactFormEvent`
  * `MailBeforeSentEvent`
  * `MailBeforeValidateEvent`
  * `MailErrorEvent`
  * `MailSentEvent`
  * `MediaUploadedEvent`
  * `NewsletterRegisterEvent`
  * `ReviewFormEvent`
  * `ProductExportLoggingEvent`
  * `UserRecoveryRequestEvent`
___
# Upgrade Information
## Deprecated diverse flow storer classes and interfaces in favor of the new `ScalarValuesStorer` class 
We deprecated diverse flow storer interfaces in favor of the new `ScalarValuesAware` class. The new `ScalarValuesAware` class allows to store scalar values much easier for flows without implementing own storer and interface classes. 
If you implemented one of the deprecated interfaces or implemented an own interface and storer class to store simple values, you should replace it with the new `ScalarValuesAware` class. 

```php

// before
class MyEvent extends Event implements \Shopware\Core\Content\Flow\Dispatching\Aware\UrlAware
{
    private string $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
    
    // ...
}

// after

class MyEvent extends Event implements \Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware
{
    private string $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function getScalarValues(): array
    {
        return [
            'url' => $this->url,
            // ...
        ];
    }
    
    // ...
}
```

The deprecated flow storer interfaces are:
* `ConfirmUrlAware`
* `ContactFormDataAware`
* `ContentsAware`
* `ContextTokenAware`
* `DataAware`
* `EmailAware`
* `MediaUploadedAware`
* `NameAware`
* `RecipientsAware`
* `ResetUrlAware`
* `ReviewFormDataAware`
* `ScalarStoreAware`
* `ShopNameAware`
* `SubjectAware`
* `TemplateDataAware`
* `UrlAware`
