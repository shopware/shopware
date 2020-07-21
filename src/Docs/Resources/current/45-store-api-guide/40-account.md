[titleEn]: <>(Store api account routes)
[hash]: <>(article:store_api_account)

## Account
On this page we will show you how you can use the store-api to manage everything about your customer accounts. That includes how you can log in an log out your customers and many more things.

### Login
To login a user you can use this route: `store-api.account.login`

This route needs two parameters:
* `username`: this parameter takes the username of the user
* `password`: here you enter the password of your customer

For this example we are using the account credentials for the dummy user.

```
POST /store-api/v3/account/login

{
    "username": "test@example.com",
    "password": "shopware"
}

{
    "contextToken": "7kvktKmC5IfM83fM3sWGIv3YHBuoTECm",
    "apiAlias": "array_struct"
}
```

### Register

To register a customer you need two routes: `store-api.account.register` and `store-api.account.register.confirm`

The `store-api.account.register` needs the following parameters:
* `guest`: decides whether the account is a guest account or not
* `title`: the title of the customer e.g. 'Dr.'
* `salutationId`: the id of the salutation
* `firstName`: first name of your customer  
* `lastName`: last name of the customer
* `email`: email of the customer
* `affiliateCode`: an affiliate code 
* `campaignCode`: a campaign code
* `password`: password of the customer 
* `billingAddress`: billing address of the customer  
* `shippingAddress`: shipping address of the customer
* `storefrontUrl`: the url to your storefront 

```
POST /store-api/v3/account/register

{
    "guest": false,
    "title": "Dr.",
    "salutationId": "f4dff0c0a2cf4830a47901c5ae10819a",
    "firstName": "Eva",
    "lastName": "Mustermann",
    "email": "eva@mustermann.de",
    "affiliateCode": "",
    "campaignCode": "",
    "password": "shopware",
    "billingAddress": {
        "countryId": "34a06af5c53c4ee3846ad2ad5498dbe9",
        "street": "Examplestreet 11",
        "zipcode": "48441",
        "city": "Cologne"
    },
    "shippingAddress": {
        "countryId": "34a06af5c53c4ee3846ad2ad5498dbe9",
        "salutationId": "f4dff0c0a2cf4830a47901c5ae10819a",
        "firstName": "Eva",
        "lastName": "Mustermann",
        "street": "Examplestreet 154",
        "zipcode": "12341",
        "city": "Berlin"
    },
    "storefrontUrl": "http://shopware.local",
    "includes": {
        "customer": [
            "firstName",
            "lastName",
            "email",
            "password"
        ]
    }
}

{
    "firstName": "Eva",
    "lastName": "Mustermann",
    "email": "eva@mustermann.de",
    "apiAlias": "customer"
}
```

Whether you have double opt in registration enabled or not the account of your customer is enabled or not.

If double opt in registration is enabled you need to use this route: `store-api.account.register.confirm` to active the account of you customer.

This route needs two parameters: 
* `hash`: the hast to verify the user account
* `em`: the email of your customer

```
POST /store-api/v{version}/account/register-confirm

{
    "hash": "e43b79ef0ee5461786a3744fcff1e162",
    "email": "test@example.com",
    "includes": {
        "customer": [
            "salesChannelId",
            "customerNumber",
            "id"
        ]
    }
}

{
    "salesChannelId": "c9f8adb3cafb4ff69a4566b806300493",
    "customerNumber": "10079",
    "id": "2207e9a717854ab1affb8f57cebeead3",
    "apiAlias": "customer"
}
``` 

### Logout
Using this route `store-api.account.logout` you can log out a customer.

This route does not need any parameter.

**Note** that you need the `sw-context-token` header for this route, which contains the context token of the login route response.

```
POST /store-api/v3/account/logout

// when you get a 204 http reponse code you successfully logged out your customer.

{
    "apiAlias": "array_struct"
}
```

### Get current customer
With the following route you can get information about the logged in user: `store-api.account.customer`

Additionally can use the api basic parameters (`filter`,  `aggregations`, etc.) for more information look [here](./../40-admin-api-guide/20-reading-entities.md).

**Note** that you need the `sw-context-token` header for this route, which contains the context token of the login route response.

```
POST /store-api/v3/account/customer

{
    "includes": {
        "customer": [
            "firstName",
            "lastName",
            "active"
        ]
    }
}

{
    "firstName": "Jon",
    "lastName": "Doe",
    "active": true,
    "apiAlias": "customer"
}
```

### Change profile
With the `store-api.account.change-profile`-route you can change information about the customer.

This route takes three parameters:
* `salutationId`: the id of the salutation
* `firstName`: the new firstname of the user
* `lastName`: the new firstname of the user

**Note** that you need the `sw-context-token` header for this route, which contains the context token of the login route response.

```
POST /store-api/v3/account/change-profile

{
    "salutationId": "99362bce5d764c959289e65039d8d625",
    "firstName": "Sven",
    "lastName": "Svensson"
}

{
    "success": true,
    "apiAlias": "array_struct"
}
```

### Change email
Using the `store-api.account.change-email` route you can change the email of your customer.

It takes in three parameters:
* `email`: the new email the account should get
* `emailConfirmation`: this parameter confirm that the email the customer entered is correct. The value has to be the sames as the value for the `email` parameter.
* `password`: the password of the customer

**Note** that you need the `sw-context-token` header for this route, which contains the context token of the login route response.

```
POST /store-api/v3/account/change-email

{
    "email": "jon.doe@example.com",
    "emailConfirmation": "jon.doe@example.com",
    "password": "shopware"
}

{
    "success": true,
    "apiAlias": "array_struct"
}
```

### Change password
When you need to change the password of an user you can use this route: `store-api.account.change-password`

The parameters for this route are:
* `password`: here you enter the old password
* `newPassword`: this parameters takes the new password
* `newPasswordConfirm`: and this parameters confirms the new password. It does so by being the same value that in used for the `newPassword` parameter.

**Note** that you need the `sw-context-token` header for this route, which contains the context token of the login route response.

In this example we change the password with a new password that is more secure than the old one.

```
POST /store-api/v3/account/change-password

{
    "password": "password",
    "newPassword": "C@ebvRPy*r!gxXKu6p_mmkT_",
    "newPasswordConfirm": "C@ebvRPy*r!gxXKu6p_mmkT_"
}

{
    "success": true,
    "apiAlias": "array_struct"
}
```

### Reset password
When a user forgets his password you can use these two routes: `store-api.account.recovery.password` and `store-api.account.recovery.send.mail`

First we send an password reset verification email to the user by sending this request.

The `store-api.account.recovery.send.mail` has two parameters:
* `email`: this parameter needs the email address the customer
* `storefrontUrl`: for this parameter you enter the base path to the Sales Channel

```
POST /store-api/v3/account/recovery-password

{
    "email": "jon.doe@example.com",
    "storefrontUrl": "http://shopware.local"
}

{
    "success": true,
    "apiAlias": "array_struct"
}
```

After that, you do a request on this route `store-api.account.recovery.password`.

It needs the following parameters:
* `hash`: here you need the hash that you got from the password recovery email
* `newPassword`: this is the parameter for the new password of the customer account
* `newPasswordConfirm`: with this parameter you confirm that the password the user entered is correct.
    * That means this parameter needs to have the same password that is used for the `newPassword` parameter.
* `storefrontUrl`: this parameters needs the base url of the Sales Channel

```
POST /store-api/v3/account/recovery-password-confirm

{
    "hash": "J18339ctUmiD82fSxsPU0VnOmhEG4XXt",
    "newPassword": "newPassword",
    "newPasswordConfirm": "newPassword",
    "storefrontUrl": "http://shopware.local"
}

{
    "success": true,
    "apiAlias": "array_struct"
}
```

### Change default payment
You can change the default payment method of an customer with this route: `store-api.account.set.payment-method`

This route has a parameter the following parameter:
* `paymentMethodId`: This parameter determines which payment method will be the new default payment method for this customer.

**Note** that you need the `sw-context-token` header for this route, which contains the context token of the login route response.

```
POST /store-api/v3/account/change-payment-method/da4aa20cd7b9417094a0eb51426f0912

{
    "success": true,
    "apiAlias": "array_struct"
}
```

### Order overview
You can view an order of the customer with the `store-api.order` route.

Additionally can use the api basic parameters (`filter`,  `aggregations`, etc.) for more information look [here](./../40-admin-api-guide/20-reading-entities.md).

**Note** that you need the `sw-context-token` header for this route, which contains the context token of the login route response.

```
GET /store-api/v3/order

{
    "includes": {
        "order": [
            "orderNumber",
            "orderDateTime",
            "price"
        ],
        "cart_price": [
            "netPrice",
            "totalPrice",
            "taxStatus"
        ]
    }
}

{
    "total": 1,
    "aggregations": [],
    "elements": [
        {
            "orderNumber": "10061",
            "orderDateTime": "2020-04-09T06:23:56+00:00",
            "price": {
                "netPrice": 757.94,
                "totalPrice": 811,
                "taxStatus": "gross",
                "apiAlias": "cart_price"
            },
            "apiAlias": "order"
        }
    ],
    "apiAlias": "dal_entity_search_result"
}
```

### Newsletter

#### Subscribing to a newsletter

You can use the `store-api.newsletter.subscribe` route to sign up customer to an newsletter.

This route has a few parameters:
* `email`: the email of the customer
* `salutationId`: id of a salutation
* `firstName`: the first name of the customer
* `lastName`: the last name of the customer 
* `street`: street address of the customer 
* `city`: city of the customer
* `zipCode`: zip code of the customer
* `option`: the type of the email
* `storefrontUrl`: url to your storefront

```
POST /store-api/v3/newsletter/subscribe

{
    "email": "test@example.com",
    "salutationId": "306f47866a8c4089bcbec14a10f19e0d",
    "firstName": "Jon",
    "lastName": "Doe",
    "street": "Random Street",
    "city": "San Francisco",
    "zipCode": "12345",
    "option": "subscribe",
    "storefrontUrl": "http://shopware.local"
}

{
    "apiAlias": "array_struct"
}
```

#### Unsubscribe customer from newsletter

You can unsubscribe your customer from a newsletter with this route: `store-api.newsletter.unsubscribe`

This route has only one parameter: 
* `email`: the email of the customer

```
POST /store-api/v3/newsletter/unsubscribe

{
    "email": "test@example.com"
}

{
    "apiAlias": "array_struct"
}
```

### Contact form
Use this route `store-api.contact.form` if you want that customers can send messages to your shop.

This route needs a few parameters:
* `salutationId`: here you need to enter the id of the salutation of your customer
* `firstName`: this parameter takes in the firstname of your customer 
* `lastName`: this parameter needs the lastname of your customer 
* `email`: here you enter the which email address should receive the message
* `phone`: for this parameter you enter the phone number of your customer 
* `subject`: this parameters determines the parameter for the subject of the email 
* `comment`: this parameters takes the actual message the customer wants to send you

```
POST /store-api/v3/contact-form

{
    "salutationId": "99362bce5d764c959289e65039d8d625",
    "firstName": "Jon",
    "lastName": "Doe",
    "email": "test@example.com",
    "phone": "0180 - 000000",
    "subject": "Lorem ipsum",
    "comment": "Lorem ipsum dolor sit amet, consectetur adipisicing elit."
}

{
    "individualSuccessMessage": "",
    "apiAlias": "contact_form_result"
}
```
