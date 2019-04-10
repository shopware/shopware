[titleEn]: <>(SalesChannel-API cart endpoint)

The cart endpoint is used for various cart operations like adding line items to the cart, removing them,
change their quantity and placing an order.

## Create an empty cart

**POST  /sales-channel-api/v1/checkout/cart**  
**Response:** If successful, the x-sw-context-token will be returned and the x-sw-context-token header will be set.
Include this token as an HTTP header for all future requests.

## Get a cart

**GET  /sales-channel-api/v1/checkout/cart**

**Header:** x-sw-context-token is required

**Response:** If successful, the calculated cart will be returned. 

## Add product to cart

**POST  /sales-channel-api/v1/checkout/cart/product/{id}**

**Header:** x-sw-context-token is required

**Parameter:**

| Name     | Type  | Notes      | Required |
| -------- | ----- | ---------- | -------- |
| quantity | int   | Default: 1 |          |
| payload  | array |            |          |

**Header:** x-sw-context-token is required

**Response:** If successful, the calculated cart will be returned.

## Add line item to cart

**POST  /sales-channel-api/v1/checkout/cart/line-item/{id}**

**Header:** x-sw-context-token is
required
**Parameter:**

| Name        | Type    | Notes                                                                                                           | Required |
| ----------- | ------- | --------------------------------------------------------------------------------------------------------------- | :------: |
| type        | string  |                                                                                                                 |    ✔     |
| payload     | array   |                                                                                                                 |          |
| quantity    | int     | Default: 1                                                                                                      |          |
| stackable   | boolean | Default: false, if set to true, quantity cannot be changed                                                      |          |
| removable   | boolean | Default: false, if set to true, line items cannot be removed from cart                                          |          |
| priority    | int     | Order in which the line items will be processed and calculated. The default is 100. Lower priority comes first. |          |
| label       | string  |                                                                                                                 |          |
| description | string  |                                                                                                                 |          |
| coverId     | uuid    | UUID of a media entity                                                                                          |          |

**Header:** x-sw-context-token is required

**Response:** If successful, the calculated cart will be returned.

## Remove line item from cart

**DELETE  /sales-channel-api/v1/checkout/cart/line-item/{id}**

**Header:** x-sw-context-token is required

**Response:** If successful, the calculated cart will be returned.

## Update line item quantity

**PATCH 
/sales-channel-api/v1/checkout/cart/line-item/{id}/quantity/{quantity}**

**Header:** x-sw-context-token is required

**Response:** If successful, the calculated cart will be returned.

## Update line item

**PATCH  /sales-channel-api/v1/checkout/cart/line-item/{id}**

**Header:** x-sw-context-token is required

**Parameter:**

| Name        | Type    | Notes                                                                                                           | Required |
| :---------- | ------- | --------------------------------------------------------------------------------------------------------------- | -------- |
| payload     | array   |                                                                                                                 |          |
| quantity    | int     | Default: 1                                                                                                      |          |
| stackable   | boolean | Default: false, if set to true, quantity cannot be changed                                                      |          |
| removable   | boolean | Default: false, if set to true, line items cannot be removed from the cart                                      |          |
| priority    | int     | Order in which the line items will be processed and calculated. The default is 100. Lower priority comes first. |          |
| label       | string  |                                                                                                                 |          |
| description | string  |                                                                                                                 |          |
| coverId     | uuid    | UUID of a media entity                                                                                          |          |

**Response:** If successful, the calculated cart will be returned.

## Create an order

**POST  /sales-channel-api/v1/checkout/order**

**Header:** x-sw-context-token is required

**Response:** If successful, the order will be returned.

## Create a guest order

**POST  /sales-channel-api/v1/checkout/guest-order**

**Header:** x-sw-context-token is required

**Parameter:** For the parameter, please have a look at the [customer registration](/en/shopware-platform-en/using-the-sales-channel-api/customer-endpoint?category=shopware-platform-en/core-components/using-the-sales-channel-api).
The guest parameter is always set to true.

**Response:** If successful, the order will be returned.

## Start the payment process for an order

**POST  /sales-channel-api/v1/checkout/pay/order/{orderId}**

**Header:** x-sw-context-token is required

**Parameter:** If *finishUrl* is set, the customer will be redirected to this URL after the payment process is completed. 

**Response:** The response depends on the type of the payment processor used.
A payment processor can define if the user needs to be redirected to an external payment gateway.
If that's the case, you get a response which includes a paymentUrl.
This is the URL of the external payment gateway where you have to redirect the user to.
If the payment process is completed or the payment processor use an external payment gateway, you will receive an empty response.

## Get guest order by a deep link

**GET  /sales-channel-api/v1/checkout/guest-order/{id}**

**Parameter:** The *accessCode* parameter is required and will be returned when a guest order is placed.

**Response:** If successful, the order will be returned.

## Full example
```javascript
    const accessKey = '{insert your storefront access key}';
    const baseUrl = '{insert your url}';
    
    let customer = {
        firstName: 'Max',
        lastName: 'Mustermann',
        email: 'max.mustermann@example.com',
        billingStreet: 'Buchenweg 5',
        billingZipcode: '33602',
        billingCity: 'Bielefeld'
    };
    let headers = {
        "Content-Type": "application/json",
        "X-SW-Access-Key": accessKey
    };
    
    function initCart() {
        const init = { method: 'POST', headers };
        return fetch(baseUrl + '/sales-channel-api/v1/checkout/cart', init)
            .then((resp) => resp.json())
            .then(({ 'x-sw-context-token': contextToken }) => {
                headers['x-sw-context-token'] = contextToken;
            });
    }
    
    function getCart() {
        const init = { method: 'GET', headers };
        return fetch(baseUrl + '/sales-channel-api/v1/checkout/cart', init)
            .then((resp) => resp.json());
    }
    
    function addProductToCart(productId) {
        const url = `${baseUrl}/sales-channel-api/v1/checkout/cart/product/${productId}`;
        return fetch(url, { method: 'POST', headers })
            .then((resp) => resp.text());
    }
    
    function getProducts() {
        return fetch(baseUrl + '/sales-channel-api/v1/product', { headers })
            .then((resp) => resp.json())
            .then(({ data }) => data)
    }
    
    function changeLineItemQuantity(key, quantity) {
        const url = `${baseUrl}/sales-channel-api/v1/checkout/cart/line-item/${key}/quantity/${quantity}`;
        return fetch(url, { method: 'PATCH', headers })
            .then((resp) => resp.json());
    }
    
    function getCountry(iso3) {
        const url = `${baseUrl}/sales-channel-api/v1/sales-channel/countries?filter[iso3]=${iso3}`;
        return fetch(url, { method: 'GET', headers })
            .then((resp) => resp.json())
            .then(({ data }) => data[0]);
    }
    
    function guestOrder(customer) {
        const url = `${baseUrl}/sales-channel-api/v1/checkout/guest-order`;
        const body = JSON.stringify(customer);
        return fetch(url, { method: 'POST', headers, body })
            .then((resp) => resp.json())
            .then(({ data }) => data);
    }
    
    function getGuestOrder(orderId, accessCode) {
        const url = new URL(`${baseUrl}/sales-channel-api/v1/checkout/guest-order/${orderId}`);
        url.searchParams.append('accessCode', accessCode);
    
        return fetch(url, { method: 'GET', headers })
            .then((resp) => resp.json())
            .then(({ data }) => data);
    }
    
    async function checkoutExample() {
        await initCart();
        console.log('Cart', await getCart());
    
        const [p1, p2] = await getProducts();
        await addProductToCart(p1.id);
        await addProductToCart(p2.id);
        let cart = await getCart();
        console.log('Cart', cart);
    
        const lineItem = cart.data.lineItems[0];
        await changeLineItemQuantity(lineItem.key, 2);
        console.log('Cart', await getCart());
    
        const country = await getCountry('deu');
    
        customer['billingCountry'] = country.id;
        const order = await guestOrder(customer);
        console.log('Order', order);
    
        console.log('Order by access code', await getGuestOrder(order.id, order.deepLinkCode))
    }
    
    checkoutExample().then(() => {
        console.log('Checkout completed');
    });
```
