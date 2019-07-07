[titleEn]: <>(Breaking change - Change default timezone to UTC)

On kernel construct, the platform now uses UTC as default timezone.
This means that all dates in the database are now also UTC. The administration formats the DateTime
objects correctly by default by simply using the `|date` filter.

In the storefront we have a Timezone utility which automatically detects the timezone of the 
user and sets a cookie which will be processed by the 
`platform/src/Storefront/Framework/Twig/TwigDateRequestListener.php`. This means that you can use 
the `|localizeddate('short', 'none', app.request.locale)` filter and the time will 
automatically be converted to the client timezone.

If you have to convert the time on the client side, you can use the DateFormatPlugin.
Example: 
```
<span data-date-format="true">
    {{ order.orderDateTime.format(shopware.dateFormat) }}
</span>
```
