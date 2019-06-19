[titleEn]: <>(Cart refactoring)

To handle dynamic line items, which has a dependency to already added cart line items, the extension points for the cart processor has changed.
There are two new interface for the cart:

* \Shopware\Core\Checkout\Cart\CartProcessorInterface
* \Shopware\Core\Checkout\Cart\CartDataCollectorInterface

The `CartDataCollectorInterface` is an replacement for the previous `CollectorInterface`. Instead of splitting the data fetching and enrichment into three steps we decided to merge this steps into a single one to keep it more simple.
The provided `StructCollection $data` persists for a request. This should prevent to fetching the same entity data multiple times inside a single request.

By adding the `CartProcessorInterface` we implemented a new extension point for the cart process. It allows to get access to the different subtotals of the cart. This processors can be registered in the di container: `<tag name="shopware.cart.processor" priority="xxx" />`.
This allows to add an additional calculation step after the products calculated and calculate simple a discount for the previous calculated products.

***Important change***: Line items which are not handled by a processor will be removed from the cart. It is required to implement a `CartProcessorInterface` for each kind of line items.

What should a processor do?  
There are two different kinds of processors:

1. Lets call it `static elements processor`
A static elements processors handles line items which added to the cart over sources which are called outside the cart process.
The `ProductCartProcessor` is the best example for this kind of processors.
Products added over the `CartLineItemController` controller and will be handled by the `ProductCartProcessor`. This fetches the required data over the gateway and enrich the line items with labels, description or prices.
Additionally the processor is responsible to transfer the product line items of the provided `Cart $original` to the provided `Cart $toCalculate`. Otherwise the products would be removed from the cart.

2. Lets call it `dynamic element processor`
A dynamic element processor has no line items which are added to the cart which has to be enriched, calculated and transferred. This kind of processor checks for a specify cart/context state and adds own line items to the cart if the state reached.
The PromotionCartProcessor is the best example for this kind of processors.
It checks if a cart state reached (for example: Cart amount > 500), and calculates a discount for -10% for the already added line items.
Instead of checking the provided `Cart $original`, this kind of processor works only with the provided `Cart $toCalculate` to check if a corresponding state reached.

This new pattern simplifies the way to working with the cart for the `dynamic element` situation. Processors of this kind, has no more to validate if their are already discounts of their own type which are in conflict with the current state. 
