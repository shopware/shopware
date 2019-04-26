[titleEn]: <>(Renamed CheckoutContext to SalesChannelContext)


We renamed the `CheckoutContext` to `SalesChannelContext` and moved `Checkout\Context` to `System\SalesChannelConte

In perspective it is planned to

* move all SalesChannel related classes from `Framework` to `System\SalesChannel`
* Rename the StoreFront files in the Core to SalesChannel
* Rename the API-Routes
* but keep the Controllers / Services / Repositories in the corresponding domain modules


As always: **sorry for the inconvenience!**
