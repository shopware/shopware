# Price actions api

The price actions api endpoint is a simple api endpoint which allows different price operations.

## Calculation api
The `api/price/actions/calculate` endpoint allows to trigger the price calculation.
Following parameters are required:
* (float) price:base price for the calculation
* (string) taxId:tax id which should be used for calculation

Following parameter can be provided for different configurations:
* (int) quantity:allows to calculate prices with more than one quantity
* (string) output (*net*/*gross*):to calculate a net or gross price
* (bool) calculated:defines that the price is already the end unit price

As a response, the endpoint returns a json encoded `Shopware\Cart\Price\Struct\CalculatedPrice` object:

### Net to gross calculation

**input** 
```json
{
  "price": 10,
  "taxId": "57f4ecb1162843e69dabc4a6a7b66eab",
  "calculated": false
}
```


**output**
```json
{
  "unitPrice":11.9,
  "quantity":1,
  "totalPrice":11.9,
  "calculatedTaxes":[
    {
      "tax":1.9,
      "taxRate":19,
      "price":11.9,
      "extensions":[]
    }
  ],
  "taxRules":[
    {
      "rate":19,
      "percentage":100,
      "extensions":[]
    }
  ],
  "extensions":[]
}
```


### Gross to net calculation
**input**
```json
{
  "price":11.9,
  "output":"net",
  "taxId":"57f4ecb1162843e69dabc4a6a7b66eab",
  "calculated":true
}
```


**output**
```json
{
  "unitPrice":11.9,
  "quantity":1,
  "totalPrice":11.9,
  "calculatedTaxes":[
    {
      "tax":2.26,
      "taxRate":19,
      "price":11.9,
      "extensions":[]
    }
  ],
  "taxRules":[
    {
      "rate":19,
      "percentage":100,
      "extensions":[]
    }
  ],
  "extensions":[]
}
```

### Net to net calculation

**input**
```json
{
  "price":10.002,
  "output":"net",
  "taxId":"57f4ecb1162843e69dabc4a6a7b66eab",
  "calculated":false
}
```


**output**
```json
{
  "unitPrice":10,
  "quantity":1,
  "totalPrice":10,
  "calculatedTaxes":[
    {
      "tax":1.9,
      "taxRate":19,
      "price":10,
      "extensions":[]
    }
  ],
  "taxRules":[
    {
      "rate":19,
      "percentage":100,
      "extensions":[]
    }
  ],
  "extensions":[]
}
```

### Gross to gross calculation
**input**
```json
{
  "price":11.9,
  "taxId":"57f4ecb1162843e69dabc4a6a7b66eab",
  "calculated":true
}
```


**output**
```json
{
  "unitPrice":11.9,
  "quantity":1,
  "totalPrice":11.9,
  "calculatedTaxes":[
    {
      "tax":1.9,
      "taxRate":19,
      "price":11.9,
      "extensions":[]
    }
  ],
  "taxRules":[
    {
      "rate":19,
      "percentage":100,
      "extensions":[]
    }
  ],
  "extensions":[]
}
```


### Net to gross with quantity calculation
**input**
```json
{
  "price":10,
  "quantity":2,
  "taxId":"57f4ecb1162843e69dabc4a6a7b66eab",
  "calculated":false
}
```


**output**
```json
{
  "unitPrice":11.9,
  "quantity":2,
  "totalPrice":23.8,
  "calculatedTaxes":[
    {
      "tax":3.8,
      "taxRate":19,
      "price":23.8,
      "extensions":[]
    }
  ],
  "taxRules":[
    {
      "rate":19,
      "percentage":100,
      "extensions":[]
    }
  ],
  "extensions":[]
}
```