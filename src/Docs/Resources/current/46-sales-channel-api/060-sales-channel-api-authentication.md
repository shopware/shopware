[titleEn]: <>(SalesChannel-API authentication)
[hash]: <>(article:api_sales_channel)

The SalesChannel-API is part of our API family. It allows access to all sales channel operations, such as creating new customers, customer login
and logout, various cart operations and a lot more.

It's ideal if you want to build your own storefront. You could create a mobile app based on the Sales Channel API or just embed it into your
existing application to have a solid base for payment and transaction handling.

## Getting started

The Storefront API does only require the client\_id, which you can find in the Administration -\> Sales Channel.
The client\_id is used to identify which Sales Channel should be used.
You find more information about the concept behind the Sales Channel [here](/en/shopware-platform-en/swsaleschannel).

## Authentication

The Storefront API has no authentication since it is designed to be a public API.
Some user related endpoints require a logged in user.

The access key which you have generated above must always be included in your API request.
The custom header **sw-access-key** is used for this purpose.

## Return formats

The Storefront API supports a simple JSON formatted response similar to the Shopware 5 API.
