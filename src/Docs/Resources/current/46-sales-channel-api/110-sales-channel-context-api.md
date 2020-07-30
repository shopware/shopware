[titleEn]: <>(SalesChannel-API context endpoint)
[hash]: <>(article:api_sales_channel_context)

The `context` endpoint is used to obtain information about various entities like currency, language or country which are assigned to a
sales channel.

## Get salutations

**GET  /sales-channel-api/v3/salutation**

**Response:** Returns a list of salutations defined in the settings of your shop.

## Get currencies

**GET  /sales-channel-api/v3/currency**

**Response:** Returns a list of currencies assigned to the sales channel.
All filter, sorting, limit, and search operations are supported.
You find more information about these operations [here](./../60-references-internals/10-core/130-dal.md).

## Get languages

**GET  /sales-channel-api/v3/language**

**Response:** Returns a list of languages assigned to the sales channel.
All filter, sorting, limit, and search operations are supported.
You find more information about these operations [here](./../60-references-internals/10-core/130-dal.md).

## Get countries

**GET  /sales-channel-api/v3/country**

**Response:** Returns a list of countries assigned to the sales channel.
All filter, sorting, limit, and search operations are supported.
You find more information about these operations [here](./../60-references-internals/10-core/130-dal.md).

## Get country states

**GET  /sales-channel-api/v3/country/{countryId}/state**

**Response:** Returns a list of country states assigned to the sales channel and country.
All filter, sorting, limit, and search operations are supported.
You find more information about these operations [here](./../60-references-internals/10-core/130-dal.md).

## Get payment methods

**GET  /sales-channel-api/v3/payment-method**

**Header:** sw-context-token is required  
**Response:** Returns a list of payment methods assigned to the sales channel.
All filter, sorting, limit, and search operations are supported.
You find more information about these operations [here](./../60-references-internals/10-core/130-dal.md).

## Get shipping methods

**GET  /sales-channel-api/v3/shipping-method**

**Header:** sw-context-token is required  
**Response:** Returns a list of shipping methods assigned to the sales channel.
All filter, sorting, limit, and search operations are supported.
You find more information about these operations [here](./../60-references-internals/10-core/130-dal.md).
