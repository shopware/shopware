[titleEn]: <>(Storefront page ajax)

To secure the Storefront we made every Controller-Action inside the StorefrontBundle not requestable via XmlHttpRequests/AJAX.

You can override this by allowing XmlHttpRequests in the Route Annotation

with the `defaults={"XmlHttpRequest"=true}` Option.

Example:

```php
/**
@Route("/widgets/listing/list/{categoryId}", name="widgets_listing_list", methods={"GET"}, defaults={"XmlHttpRequest"=true})
*/
public function listAction(Request $request, SalesChannelContext $context): JsonResponse
```

For more Examples take a look inside the PageletControllers.
