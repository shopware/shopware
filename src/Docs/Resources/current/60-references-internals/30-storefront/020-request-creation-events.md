[titleEn]: <>(Request Creation Events)
[hash]: <>(article:storefront_request_events)

Started with Shopware 6.2, we introduced the [Store-API](./../../45-store-api-guide/__categoryInfo.md).
This concept adds routes for each action, which is useful in sales channels.
These routes could also be used as services in the storefront context in controllers and page loaders.
So a route is injected as service, e.g. to provide a list of orders.

The route services take a request object as parameter amongst other.
To make sure, that the user could not manipulate the result of the called routes with request parameters,
the request objects are created newly in the controllers and page loaders before calling the actual route.
E.g. only activated payment methods should be visible and selectable on the checkout confirm page.

Sometimes it is necessary to add parameters to the request, which are used deeper in the stack of Shopware.
E.g payment plugins which set payment IDs given by the payment provider, which are needed in payment handler.
To make sure these important parameters are not lost, plugins have the possibility to subscribe to certain events.

These events are located here: Storefront/Event/RouteRequest
They are named like the route service which is going to be called at this place.
E.g. the `OrderRouteRequestEvent` is dispatched right before the `OrderRoute` is called, which provides a list of orders.
The events contain the original storefront request, the newly created request for the route service and the sales channel context object.
So if a plugin needs to forward certain parameters of the request,
it could subscribe to one of these events and re-add them to the request used for the route service call.

If you are building a storefront controller or a page loader and you are using a route service,
make sure you also dispatch an event, which enables plugins to extend the request.
