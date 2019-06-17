[titleEn]: <>(Removed context from page in all storefront templates)

We removed the `context` object from the `page` object in all storefront templates.
If you need to access the `context` object it is automatically available.

## Example:

Previously:
```
{% set billingAddress = page.context.customer.defaultBillingAddress %}
```

Now:
```
{% set billingAddress = context.customer.defaultBillingAddress %}
```