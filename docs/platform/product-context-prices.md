# Product context prices

'Advanced' prices of a product are based on the **context rule system**.
If you want to configure graduations or customized (per application, language, country or even customer) prices you have to configure a context rule first.
After configuring the context rule, you can provide a new record for the `\Shopware\Api\Product\Definition\ProductContextPriceDefinition`:

```json
{
    "currencyId" : "ffffffffffffffffffffffffffffffff",
    "quantityStart" : 1,
    "quantityEnd" : 1,
    "contextRuleId" : "ffffffffffffffffffffffffffffffff",
    "price": {"gross" : 19.89, "net" : 16.69}
}
```

Each price row are defined with a currency id and a gross and net price. By providing a net and a gross price, it is possible to prevent "ugly" prices for different currencies or different tax rates.
For example:
- A gross price of 19.89 configured
- In cases that a customer should see now gross prices, he would see a price of 16.71
- With the above configuration the customer would see a price of 16.69

Another example:
- A gross price of 19.89 configured
- The customer are living in a country with a higher tax rate, for example 21% taxes
- Storing only a net price and calculation gross prices on demand, based on the active tax rules, the customer would see a price of 20.03 
- The above case would display a price of 19.89 with an inclusive tax of 3.32
 