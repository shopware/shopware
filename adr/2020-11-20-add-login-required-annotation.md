# 2020-11-20 - Add the login required annotation

## Context
Some routes for the `sales-channel-api` and the `store-api/storefront` depend on `SalesChannelContext` to identify whether the Customer is logged or not.
For keeping clean code, consistency, and more easy to readable API. We create a new annotation for routing `\Core\Framework\Routing\Annotation\LoginRequired`.

## Decision
With the `store-api/storefront` routing needs requiring logged in for access, developers need to define annotation `@LoginRequired `for API.

This annotation to the following:
* `@LoginRequired` 
    * This annotation is validating the `SalesChannelContext` has Customer return success, otherwise throw `CustomerNotLoggedInException`
* `@LoginRequired(allowGuest=true)` 
    * This annotation is validating the `SalesChannelContext` has Customer and allow Guest admits, otherwise throw `CustomerNotLoggedInException`

An example looks like the following:
```php
/**
 * @Since("6.0.0.0")
 * @LoginRequired()
 * @Route(path="/store-api/v{version}/account/logout", name="store-api.account.logout", methods={"POST"})
 */

/**
 * @Since("6.2.0.0")
 * @LoginRequired(allowGuest=true)
 * @Route("/account/order/edit/{orderId}", name="frontend.account.edit-order.page", methods={"GET"})
 */
```

## Consequences
From the moment the `LoginRequired` annotation should be using every new `store-api/storefront` if the routing needs requiring logged in for access.
If `LoginRequired` is not using, that means the `store-api/storefront` can accept without a login.
