---
title: There should be a mechanism to automatically log in on the Twig storefront when opened by a logged-in customer via Store-API.
date: 2025-03-19
area: storefront
tags: [storefront, checkout, authentication, login, customer, shopware-frontend]
---

## Context
### Current Issue
- If a customer is already logged via Store-API and opens the default Twig storefront, they are not automatically logged in. As a result, they have to log in again.

### Use Case Scenario
- In the **Digital Sales Room** plugin, when a customer adds a product to the cart and then opens the checkout page from the storefront page, they are required to log in again.

### Potential Impact
- This issue could affect other shops using the Store-API, requiring customers to log in multiple times unnecessarily.

## Decision
- We will implement a mechanism to automatically log in on the Twig storefront, if opened by a via Store-API logged-in customer.

### Backend Implementation
#### CustomerCSRFTokenManager
```php
class CustomerCSRFTokenManager
{
    public function generate(string $customerId, string $salesChannelId): string
    {
        $tokenData = [
            'salesChannelId' => $salesChannelId,
            'customerId' => $customerId,
        ];

        return $this->encrypt(hash_hmac(self::HMAC_HASH_ALGORITHM, json_encode($tokenData), $this->appSecret) . '.' . time());
    }
    
    public function validate(string $customerId, string $salesChannelId, string $token): bool
    {
        // Validate the CSRF token to ensure it is valid
        // Using `hash_equals`
        return true;
    }
}
```

#### GenerateCustomerCSRFTokenRoute
```php
#[Route(defaults: ['_loginRequired' => true])]
public function generateToken(Request $request, CustomerEntity $customer, SalesChannelContext $context): JsonResponse
{
    $csrfToken = $this->customerCsrfTokenManager->generate($customer->getId(), $salesChannelContext->getSalesChannel()->getId());

    return new JsonResponse(['token' => $csrfToken]);
}
```

#### AutomaticLoginRoute
```php

#[Route(path: '/store-api/account/login/automatic', methods: ['POST'])]
public function login(RequestDataBag $requestDataBag, SalesChannelContext $context): ContextTokenResponse
{
    $this->validateRequestDataFields(...);
    $swContextToken = $requestDataBag->getString('swContextToken');
    $session = $this->contextPersister->load($swContextToken, $context->getSalesChannel()->getId());
    $customerId = $session['customerId'];
    $salesChannelId = $session['salesChannelId'];
    if ($customerId && $context->getCustomerId() === $customerId) {
        // return if the customer is already logged in
    }

    $csrfToken = $requestDataBag->getString('csrfToken');
    $this->customerCsrfTokenManage->validate($csrfToken, $customerId, $salesChannelId);

    if ($context->getCustomer()) {
        // logout if current storefront site has been logged in by another customer
    }

    $newToken = $this->accountService->loginById(...);

    return new ContextTokenResponse($newToken);
}
```

#### AuthController
```php
namespace Shopware\Storefront\Controller;

class AuthController
{
    ... 
    
     #[Route(path: '/account/login/automatic', methods: ['POST']]
    public function loginByLoggedInCustomer(RequestDataBag $data, SalesChannelContext $context): JsonResponse
    {
        $this->automaticLoginRoute->login($data $context);
        $redirectRoute = $data->get('redirectRoute');
        
        return $this->redirectToRoute($redirectRoute);
    }   
}
```

### Shopware Frontend Implementation
automatically-login.service.ts
```javascript
generateCustomerCsrfToken() {
    const route = 'store-api/account/generated-customer-csrf-token';
    const headers = {
        ...,
        'sw-access-token': 'foo-bar',
    }
    return this.httpClient.post(
        route,
        {},
        { headers },
    );
}

redirectToSalesChannelUrl(salesChannelDomainUrl: string, csrfToken: string, customerId: string) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `${salesChannelDomainUrl}/account/login/automatic`;
    form.target = '_blank';
    document.body.appendChild(form);

    this.#createHiddenInput(form, 'csrfToken', csrfToken);
    this.#createHiddenInput(form, 'swContextToken', 'sw-access-token');
    this.#createHiddenInput(form, 'redirectRoute', 'frontend.checkout.confirm.page');

    form.submit();
    form.remove();
}
```

## Consequences
### Security
- The implementation must ensure that the automatic login mechanism is secure when performing the request from other sites by using **CSRF tokens**.

### Benefits
- The automatic login mechanism will **enhance user experience** by allowing customers who are already logged in via Store-API to access the Twig storefront without having to log in again.
