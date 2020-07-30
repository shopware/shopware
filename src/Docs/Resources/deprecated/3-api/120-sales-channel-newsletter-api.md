[titleEn]: <>(SalesChannel-API newsletter endpoint)
[hash]: <>(article:api_sales_channel_newsletter)

The newsletter endpoint is used to subscribe, confirm and unsubscribe to newsletters. It can also be used to change newsletter recipient related information.

## Subscribe to newsletters

**POST  /sales-channel-api/v3/newsletter/subscribe**

**Description:** Subscribe to a newsletter. 

**Parameter:**

| Name                                   | Type    | Notes                                                       | Required |
| ---------------------------------------| ------- | ----------------------------------------------------------- | :------: |
| email                                  | string  |                                                             |    ✔     |
| salutationId                           | uuid    |                                                             |          |
| title                                  | string  |                                                             |          |
| languageId                             | uuid    |                                                             |          |
| firstName                              | string  |                                                             |          |
| lastName                               | string  |                                                             |          |
| street                                 | string  |                                                             |          |
| zipcode                                | string  |                                                             |          |
| city                                   | string  |                                                             |          |
| customFields                           | array   |                                                             |          |

**Header:** sw-context-token is required

**Response:** Empty response if successful

## Confirm to newsletters

**POST  /sales-channel-api/v3/newsletter/confirm**

**Description:** confirmation of subscription to newsletters. 

**Parameter:**

| Name     | Type   | Notes                                               | Required |
|----------|--------|-----------------------------------------------------|:--------:|
| hash     | string | hash from subscription mail                         |    ✔     |
| em       | string | email hashed in sha1 to validate the confirmation   |    ✔     |

**Header:** sw-context-token is required

**Response:** Empty response if successful

## Unsubscribe password

**POST  /sales-channel-api/v3/newsletter/unsubscribe**

**Parameter:**

| Name     | Type   | Notes | Required |
| -------- | ------ | ----- | :------: |
| email    | string |       |    ✔     |

**Header:** sw-context-token is required

**Response:** Empty response if successful

## Update recipient information

**POST  /sales-channel-api/v3/newsletter/update**

**Parameter:**

| Name                                   | Type    | Notes                                                       | Required |
| ---------------------------------------| ------- | ----------------------------------------------------------- | :------: |
| salutationId                           | uuid    |                                                             |          |
| title                                  | string  |                                                             |          |
| languageId                             | uuid    |                                                             |          |
| firstName                              | string  |                                                             |          |
| lastName                               | string  |                                                             |          |
| street                                 | string  |                                                             |          |
| zipcode                                | string  |                                                             |          |
| city                                   | string  |                                                             |          |
| customFields                           | array   |                                                             |          |

**Header:** sw-context-token is required

**Response:** Empty response if successful

## Subscribe example

```javascript
    const accessKey = '{insert your storefront access key}';
    const baseUrl = '{insert your url}';
    
    const randomStr = Math.random().toString(36).substring(2, 15);
    
    let recipient = {
        email: `max.mustermann_${randomStr}@example.com`,
        firstName: 'Max',
        lastName: 'Mustermann',
        street: 'Buchenweg 5',
        zipcode: '33602',
        city: 'Bielefeld',
    };

    let headers = {
        "Content-Type": "application/json",
        "SW-Access-Key": accessKey
    };

    function getSalutation() {
        const url = `${baseUrl}/sales-channel-api/v3/salutation`;
        return fetch(url, { method: 'GET', headers })
            .then((resp) => resp.json())
            .then((json) => json.data[0]);
    }

    function subscribeRecipient(recipient) {
        const url = `${baseUrl}/sales-channel-api/v3/newsletter/subscribe`;
        const body = JSON.stringify(recipient);
        return fetch(url, { method: 'POST', headers, body })
            .then((resp) => resp.json())
            .then(({ data }) => data);
    }

    async function recipientExample() {
        const salutation = await getSalutation();
        recipient['salutationId'] = salutation.id;
        await subscribeRecipient(recipient);
        
        console.log('Subscribed');

    }

    recipientExample().then(() => {
        console.log('Newsletter example completed');
    });
```
