# Shop Structs and collections

## IdentityShop 
*usage: fast identification*
- IdentityShop
    - id: `string`
	- parentId: `string`
	- localeId: `string`
	- categoryId: `string`
	- paymentId: `string`
	- currencyId: `string`
	- name: `string`
	- host: `string`
	- path: `string`
	- url: `string`

- IdentityShopCollection
    - getCurrencyIds: `int[]`
    - getCategoryIds: `int[]`
    - getLocaleIds: `int[]`
    - getPaymentIds: `int[]`

## ListShop 
*usage: api lists and backends*
- ListShop extends IdentityShop   
    - categoryName: `string`
    - localeName: `string`
    - paymentName: `string`
    - currencyName: `string`
    - templateName: `string`

## DetailShop
*usage: context - all informations with dependencies*
- DetailShop extends IdentityShop
    - category: `CategoryStruct`
    - locale: `LocaleStruct`
    - payment: `PaymentStruct`
    - template: `TemplateStruct`

- DetailShopCollection 
    - getCategories: `CategoryCollection` 
    - getLocales: `LocaleCollection`
    - getPayments: `PaymentCollection`






# Customer
## IdentityCustomer 
*usage: fast identification*
- IdentityCustomer
    - id
    - number
    - email
    - active
    - firstname
    - lastname
    - billingAddressId
    - shippingAddressId
    - shopId
    - customerGroupId
    - accountType
    - paymentId

- ListCustomer
    - billingCountryName
    - shippingCountryName
    - paymentName
    - shopName
    
- DetailCustomer
    - billingAddress: `DetailAddress` 
    - shippingAddress: `DetailAddress` 
    - payment: `DetailPayment`  
    - shop: `IdentityShop`

# Address
- IdentityAddress
    - id
    - customerId
    - firstname
    - lastname
    - company
    - countryId
    - ...

- ListAddress
    - customerName
    - countryName
    - stateName

- DetailAddress
    - country: Country
    - state: Country


# Category

- CategoryIdentity
    - id
    - name
    - path
    - parent
    - ...
    
- DetailCategory
    - media
    - stream


# Product

