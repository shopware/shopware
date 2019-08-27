[titleEn]: <>(Breaking Change - Route Scopes added)

We have added Scopes for Routes. The Scopes hold and resolve information of allowed paths and contexts.
A RouteScope is mandatory for a Route. From now on every Route defined, needs a defined RouteScope.

RouteScopes are defined via Annotation:
```php
/**
 * @RouteScope(scopes={"storefront"})
 * @Route("/account/login", name="frontend.account.login.page", methods={"GET"})
 */
 
 /**
  * @RouteScope(scopes={"storefront", "my_additional_scope"})
  * @Route("/account/login", name="frontend.account.login.page", methods={"GET"})
  */

```

### RouteScope details
Current implemented RouteScopes are:
* api - *ApiRouteScope* - Scope for API-Routes. Only allowed when *AdminApiSource* is set as SourceContext and basePath begins with "/api"
* sales-channel-api - *SalesChannelApiRouteScope* - Scope for SalesChannelAPI-Routes. Only allowed when *SalesChannelApiSource* is set as SourceContext and basePath begins with "/sales-channel-api"
* administration - *AdministrationRouteScope* - Scope for Administration-Routes. Only allowed when basePath begins with "/admin".
* storeront - *StorefrontRouteScope* - Scope for Storefront-Routes. Only allowed when request is a qualified SalesChannelRequest.

When multiple scopes are defined for one Route, it is an OR conjunction - if one RouteScope matches the request, the Route will be allowed to process.

The RouteScopeInterface defines the public methods 
* *isAllowedPath(string $path):bool* to determine if a given route path is allowed. 
* *isAllowed(Request $request): bool* to determine if the given request is allowed for this route
* *getId(): string* to return the id of the ReouteScope
```php
interface RouteScopeInterface
{
    public function isAllowedPath(string $path): bool;

    public function isAllowed(Request $request): bool;

    public function getId(): string;
}
```

The isAllowedPath-method has a base implementation in AbstractRouteScope to check if the actual path begins with an allowed path of the given scope.

The isAllowed-method has to be implemented for new RouteScopes.

### RouteScopeListener
The RouteScopeListener is called on the event **KernelEvents::CONTROLLER**  and checks if the scope is allowed.
The Listener has a whitelist for Controller and/or complete namespaces to be ignored by this check.
The namespace "Smyfony\" is already whitelisted to allow redirect- and exception-controllers to work as usual without a defined RouteScope.

#### Adding whitlisted controllers  namespaces
Additional whitelisted controllers or namespaces can be added by subscribing to the event **RouteScopeEvents::ROUTE_SCOPE_WHITELIST_COLLECT**.
```php
public static function getSubscribedEvents(): array
    {
        return [
            RouteScopeEvents::ROUTE_SCOPE_WHITELIST_COLLECT => [
                ['add'],
            ],
        ];
    }

    public function add(RouteScopeWhitlistCollectEvent $event): void
    {
        $whitelist = $event->getWhitelistedControllers();
        $whitelist[] = 'MyNameSpace\Controller\OpenController';
        $whitelist[] = 'MyOpenNameSpace\*';
        $event->setWhitelistedControllers($whitelist);
    }
```
An asterisk (*) at the end of an entry whitelists the complete Namespace while no asterisk will only whitelist the specific controller.
Only use this whitlisting for controllers you need and cannot change. For your own controllers and Routes add a new RouteScope if required. 