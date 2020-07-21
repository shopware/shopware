[titleEn]: <>(SalesChannel-API customer endpoint)
[hash]: <>(article:api_sales_channel_customer)

The customer endpoint is used to register and log in customers. It can also be used to change and receive customer related information.

## Register a customer

**POST  /sales-channel-api/v3/customer**

**Description:** Register a new customer. 

**Parameter:**

| Name                                   | Type    | Notes                                                       | Required |
| ---------------------------------------| ------- | ----------------------------------------------------------- | :------: |
| salutationId                           | uuid    |                                                             |    ✔     |
| title                                  | string  |                                                             |          |
| firstName                              | string  |                                                             |    ✔     |
| lastName                               | string  |                                                             |    ✔     |
| guest                                  | bool    |                                                             |          |
| email                                  | string  |                                                             |    ✔     |
| password                               | string  | Only required when guest is false                           |          |
| birthdayDay                            | integer |                                                             |          |
| birthdayMonth                          | integer |                                                             |          |
| birthdayYear                           | integer |                                                             |          |
| billingAddress.company                 | string  |                                                             |          |
| billingAddress.department              | string  |                                                             |          |
| billingAddress.vatId                   | string  |                                                             |          |
| billingAddress.street                  | string  |                                                             |    ✔     |
| billingAddress.additionalAddressLine1  | string  |                                                             |          |
| billingAddress.additionalAddressLine2  | string  |                                                             |          |
| billingAddress.zipcode                 | string  |                                                             |    ✔     |
| billingAddress.city                    | string  |                                                             |    ✔     |
| billingAddress.countryId               | uuid    |                                                             |    ✔     |
| billingAddress.countryStateId          | uuid    |                                                             |          |
| billingAddress.phoneNumber             | string  |                                                             |          |
| shippingAddress.salutationId           | uuid    | Only required, when shippingAddress is given                |          |
| shippingAddress.firstName              | string  | Only required, when shippingAddress is given                |          |
| shippingAddress.lastName               | string  | Only required, when shippingAddress is given                |          |
| shippingAddress.company                | string  |                                                             |          |
| shippingAddress.department             | string  |                                                             |          |
| shippingAddress.vatId                  | string  |                                                             |          |
| shippingAddress.street                 | string  | Only required, when shippingAddress is given                |          |
| shippingAddress.additionalAddressLine1 | string  |                                                             |          |
| shippingAddress.additionalAddressLine2 | string  |                                                             |          |
| shippingAddress.zipcode                | string  | Only required, when shippingAddress is given                |          |
| shippingAddress.city                   | string  | Only required, when shippingAddress is given                |          |
| shippingAddress.phoneNumber            | string  |                                                             |          |
| shippingAddress.countryId              | uuid    | Only required, when shippingAddress is given                |          |
| shippingAddress.countryStateId         | uuid    |                                                             |          |

**Response:** If successful, the customerId will be returned.

## Authentication

### Log in a customer

**POST  /sales-channel-api/v3/customer/login**

**Description:** Log in a customer. 

**Parameter:**

| Name     | Type   | Notes                                               | Required |
|----------|--------|-----------------------------------------------------|:--------:|
| username | string | By default, the e-mail address is used as username  |    ✔     |
| password | string | Plain password. Hashing will be handled by Shopware |    ✔     |

**Response:** If successful, the sw-context-token will be returned. Include this token as a HTTP header for all future requests.

### Log out a customer

**POST  /sales-channel-api/v3/customer/logout**

**Header:** sw-context-token is required

**Response:** Empty response if successful

## Get a order overview

**GET  /sales-channel-api/v3/customer/order**

**Parameter:**

| Name  | Type | Notes       | Required |
| ----- | ---- | ----------- | :------: |
| limit | int  | Default: 10 |          |
| page  | int  | Default: 1  |          |

**Header:** sw-context-token is required

**Response:** List of the orders

## Update email address

**PATCH  /sales-channel-api/v3/customer/email**

**Parameter:**

| Name              | Type   | Notes | Required |
| ----------------- | ------ | ----- | :------: |
| email             | string |       |    ✔     |
| emailConfirmation | string |       |    ✔     |

**Header:** sw-context-token is required

**Response:** Empty response if successful

## Update password

**PATCH  /sales-channel-api/v3/customer/password**

**Parameter:**

| Name     | Type   | Notes | Required |
| -------- | ------ | ----- | :------: |
| password | string |       |    ✔     |

**Header:** sw-context-token is required

**Response:** Empty response if successful

## Update profile information

**PATCH 
/sales-channel-api/v3/customer**

**Parameter:**

| Name          | Type   | Notes                                               | Required |
| ------------- | ------ | --------------------------------------------------- | :------: |
| firstName     | string |                                                     |          |
| lastName      | string |                                                     |          |
| title         | string |                                                     |          |
| salutation    | string |                                                     |          |
| birthdayDay   | int    | Required if one of the other birthday fields is set |          |
| birthdayMonth | int    | Required if one of the other birthday fields is set |          |
| birthdayYear  | int    | Required if one of the other birthday fields is set |          |

**Header:** sw-context-token is required

**Response:** Empty response if successful

## Get detailed customer information

**GET  /sales-channel-api/v3/customer**

**Header:** sw-context-token is required

**Response:** List of all customer related information

## Address management

### Get customer addresses

**GET /sales-channel-api/v3/customer/address**

**Header:** sw-context-token is required

**Response:** List of all customer addresses

### Get customer address

**GET /sales-channel-api/v3/customer/address/{id}**

**Header:** sw-context-token is required

**Response:** Detailed information about the specified address id.
Note: The address id must be assigned with the customer currently logged in.

### Create customer address

**POST /sales-channel-api/v3/customer/address**

**Header:** sw-context-token is required

**Parameter:**

| Name          | Type   | Notes                                               | Required |
| ------------- | ------ | --------------------------------------------------- | :------: |
| id            | uuid   |                                                     |          |
| lastName      | string |                                                     |          |
| title         | string |                                                     |          |
| salutation    | string |                                                     |          |
| birthdayDay   | int    | Required if one of the other birthday fields is set |          |
| birthdayMonth | int    | Required if one of the other birthday fields is set |          |
| birthdayYear  | int    | Required if one of the other birthday fields is set |          |

### Delete customer address

**DELETE /sales-channel-api/v3/customer/address/{id}**

**Note:** You can not delete a default shipping or billing address.

**Header:** sw-context-token is required

### Set default shipping address

**PATCH  /sales-channel-api/v3/customer/address/{id}/default-shipping**

**Header:** sw-context-token is required

**Response:** AddressId if successful

### Set default billing address

**PATCH  /sales-channel-api/v3/customer/address/{id}/default-billing**

**Header:** sw-context-token is required

**Response:** AddressId if successful

## Full example

```javascript
    const accessKey = '{insert your storefront access key}';
    const baseUrl = '{insert your url}';
    
    const randomStr = Math.random().toString(36).substring(2, 15);
    
    let customer = {
        email: `max.mustermann_${randomStr}@example.com`,
        firstName: 'Max',
        lastName: 'Mustermann',
        billingAddress: {
            street: 'Buchenweg 5',
            zipcode: '33602',
            city: 'Bielefeld'
        },
        password: 'UNSECURE_PASSWORD'
    };

    let headers = {
        "Content-Type": "application/json",
        "SW-Access-Key": accessKey
    };

    function getCountry(iso3) {
        const url = `${baseUrl}/sales-channel-api/v3/country?filter[iso3]=${iso3}`;
        return fetch(url, { method: 'GET', headers })
            .then((resp) => resp.json())
            .then((json) => json.data[0]);
    }

    function getSalutation() {
        const url = `${baseUrl}/sales-channel-api/v3/salutation`;
        return fetch(url, { method: 'GET', headers })
            .then((resp) => resp.json())
            .then((json) => json.data[0]);
    }

    function registerCustomer(customer) {
        const url = `${baseUrl}/sales-channel-api/v3/customer`;
        const body = JSON.stringify(customer);
        return fetch(url, { method: 'POST', headers, body })
            .then((resp) => resp.json())
            .then(({ data }) => data);
    }

    function login(username, password) {
        const url = `${baseUrl}/sales-channel-api/v3/customer/login`;
        const body = JSON.stringify({ username, password });
        return fetch(url, { method: 'POST', headers, body })
            .then((resp) => resp.json())
            .then(({ 'sw-context-token': token }) => {
                headers['sw-context-token'] = token;
            });
    }

    function logout() {
        const url = `${baseUrl}/sales-channel-api/v3/customer/logout`;
        return fetch(url, { method: 'POST', headers })
            .then((resp) => resp.text())
            .then(() => { headers['sw-context-token'] = null });
    }

    function getProfile() {
        const url = `${baseUrl}/sales-channel-api/v3/customer`;
        return fetch(url, { method: 'GET', headers })
            .then((resp) => resp.json())
            .then(({ data }) => data);
    }

    async function customerExample() {
        const country = await getCountry('deu');
        customer['billingAddress']['countryId'] = country.id;

        const salutation = await getSalutation();
        customer['salutationId'] = salutation.id;
        await registerCustomer(customer);

        await login(customer.email, customer.password);
        console.log('Context-Token', headers['sw-context-token']);

        console.log('Profile', await getProfile());

        await logout();
        console.log('Context-Token', headers['sw-context-token']);
    }

    customerExample().then(() => {
        console.log('Customer example completed');
    });
```
