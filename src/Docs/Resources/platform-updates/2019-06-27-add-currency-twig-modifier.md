[titleEn]: <>(Add currecny twig modifier)

We added a convenient method to format a price in the Storefront. Before we introduced the modifier it was quite
inconvenient to format numbers using the built in filter:

```
{{ page.cart.price.positionPrice|currency(context.currency.translated.shortName, app.request.locale) }}{{ "general.star"|trans }}
``` 

Now the new filter automatically detects it's context, the currently used currency and locale. We haven't added the star
snippet due to the fact you may don't want the symbol in the checkout process:

```
{{ page.cart.price.positionPrice|currency }}{{ "general.star"|trans }}
```

