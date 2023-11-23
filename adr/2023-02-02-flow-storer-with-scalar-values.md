---
title: Flow storer with scalar values
date: 2023-02-02
area: core
tags: [flow, storer, scalar, deprecation]
---

## Context
At the moment we have a bunch of different `FlowStorer` implementations. Most of them are used to store scalar values without any restore logic. Each of the Storer class has an own interface which is used to identify if the data of the event should be stored. This leads to much boilerplate code when adding new storer implementations or when plugins want to bypass some for events. 

## Decision

We introduce a generic `ScalarValuesAware` interface which can be used to store simple values which should be simply stored and restored one to one:

```php
interface ScalarValuesAware
{
    public const STORE_VALUES = 'scalar_values';
    
    /** @return array<string, scalar|null|array> */
    public function getValues(): array;
}
```

This event can be used in different events which needs a simple storage logic:

```php
class SomeFlowAwareEvent extends Event implements ScalarStoreAware, FlowEventAware
{
    public function __construct(private readonly string $url) { }

    public function getValues(): array
    {
        return ['url' => $this->url];
    }
}
```

To store and restore this data, we provide a simple `FlowStorer` implementation:

```php

class ScalarValuesStorer extends FlowStorer
{
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof ScalarValuesAware) return $stored

        $stored[ScalarValuesAware::STORE_VALUES] = $event->getValues();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        $values = $storable->getStore(ScalarValuesAware::STORE_VALUES);
        foreach ($values as $key => $value) {
            $storable->setData($key, $value);
        }
    }
}
```

## Consequences
- It is no more necessary to implement storer classes to just store and restore scalar values.
- We deprecate all current `Aware` interface and `Storer` classes which can simply replaced by this new implementation
  - Following storer and interfaces will be deprecated:
    - ConfirmUrlStorer > ConfirmUrlAware
    - ContactFormDataStorer > ContactFormDataAware
    - ContentsStorer > ContentsAware
    - ContextTokenStorer > ContextTokenAware
    - DataStorer > DataAware
    - EmailStorer > Email Aware
    - MailStorer > Mail Aware
    - NameStorer > NameAware
    - RecipientsStorer > RecipientsAware
    - ResetUrlStorer > ResetUrlAware
    - ReviewFormDataStorer > ReviewFormDataAware
    - ShopNameStorer > ShopNameAware
    - SubjectStorer > SubjectAware
    - TemplateDataStorer  > TemplateDataAware
    - UrlStorer > UrlAware
- Affected events will be updated to use the new `ScalarStoreAware` interface. 
- Existing `*Aware` events will stay in the event implementation and will be marked as deprecated.
- Developers can much easier store and restore values without providing a lot of boilerplate code.
- Deprecated classes will be removed in v6.6.0.0
- All interface and storer logic will remain until the next major and has to be compatible with each other
