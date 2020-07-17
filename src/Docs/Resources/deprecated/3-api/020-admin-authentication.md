[titleEn]: <>(Admin API authentication)
[hash]: <>(article:api_admin_auth)

The Admin API uses the [oauth2](https://oauth.net/2/) standard to authenticate clients.

## Access token

A valid access token is required to access any resource.
It can be obtained by sending a valid authentication request to **/api/oauth/token**.

There are two methods available for the authentication:

### Password authentication

The password authentication is intended for actual end users.
It comes with a user session and therefore can be used in web clients.

### Client credential authentication

The client credential authentication is intended for backend service clients without a user session.
It should not be used in a web client, because it's not safe to share the secret.

To use the client authentication method, you to need to create an integration.
The integrations can be managed in the administration client under **Settings → Integrations**.

### Scopes

The access permissions are controlled by **scopes**. To get write access you need to request the **write** scope.
If you do not request any scope, you get read-only access.

### Bearer token

The access token is passed along as a bearer token in the **Authorization** header.
The value looks like **Bearer {access\_token}**.
For example:

```Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImNmZWQ3NWU1N...```

## Password authentication

### Request body

```json
    {
        "client_id": "administration",
        "grant_type": "password",
        "scopes": "write",
        "username": "admin",
        "password": "shopware"
    }
```

The authentication request requires the following data:

|    Name     |  Type  |                   Usage                      |
| ----------- | ------ | -------------------------------------------- |
| client\_id  | string | The client identifier                        |
| grant\_type | string | The authentication method                    |
| scopes      | string | The requested permissions                    |
| username    | string | The username for the password authentication |
| password    | string | The password for the password authentication |

### Response body

```json
    {
        "token_type": "Bearer",
        "expires_in": 3600,
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6Ijg3MmQzNGE5MWY5NGUwODU1YjdjNGJhNWQ2NTcwMzEzYjQ3NTg2OGZmMzdlNTBiZWY2NTJmODc1OTAxODI1MmUwZWQ3Y2M5NDljMjQwZDVlIn0.eyJhdWQiOiJhZG1pbmlzdHJhdGlvbiIsImp0aSI6Ijg3MmQzNGE5MWY5NGUwODU1YjdjNGJhNWQ2NTcwMzEzYjQ3NTg2OGZmMzdlNTBiZWY2NTJmODc1OTAxODI1MmUwZWQ3Y2M5NDljMjQwZDVlIiwiaWF0IjoxNTM2ODI3OTY3LCJuYmYiOjE1MzY4Mjc5NjcsImV4cCI6MTUzNjgzMTU2Nywic3ViIjoiNjM5YWJkZWIwMGMyNDFmOTgwYzllZWY0NDVjOWIzOWIiLCJzY29wZXMiOlsid3JpdGUiLCJ3cml0ZSJdfQ.U05dmw300bDbW6onTwIkdewFztbcdTSucnh-13s-tPZkOHh5liGzZ2enstIfY4qaucY1okFjBXRxVnFVXD21YYwY0SsWMufxwdrfDONVcGKXQlbD7zOZw8-WYNuAsAFkKuuCFSkrzXxmBHkchDUWI4o0s3RzFCnX_N-GxBnB6U-oPjjXhqASG3c1OZY9PRuEHQspi2mA2TDouSTnpS8rvM90vhnZR1UNz5HDEKQuG6MyNDfPmvc5n6mjItIQDgQC-jGB9BEW5ISP2ma4CKtvTL04CDlnld2s_O0JLqx3FbKIkT-3wD4sRPtw8bHdEOYWfBeroxRYJiFPiJ1cx_32wA",
        "refresh_token": "def50200eae2708c871cd3b9b28b05053d59f5981a35674394bf4fc425e7a7b98b9ed1327c6d2317bbb97357ea244a0e014e9c797da074cb2f145588afa58eb3a04e25f511cc22a654b44f1ce694fea52bfd5790da5374fb0f6d343a636efc7d88c4336900e71597b5378f583bdc51006a5c523b3b7121d7ad0e34bf23a60cd794a8b206ac91606726f69399d5d1f24ffb21527b9d502c3f363b8f2acd9c44feaf3eb0bdbef4c7f08300b409fe61aef9853cd6d05bb958cdc936bb29101d01c4378f8e1253196d694a024fe67663db0211dedb2737502f39ce068da1771d1c53715378d06105b2d477c68a0aaa571994e2b75860cfcb46f758a383222cbd59c3d0ce279b60370eab0dcd6518da3bdae28266b06bc811c0d4b7182cb8925df4b329140dec0dc11632749c825d5dd3cd5ad37a57947fe7d42e0ddc712f8dc07d3e09cea958eb7236a269b505b4ce00a3548366cd82664357a4001e9f709d673adae8c5780b11484ef80a481ce49ca3ad6598f589f8a306332c8105b641c2260493e38f0f3431847fef670f790f30f6457654daa9378c6081e5f1cd206c95c4"
    }
```

The response has the following structure:

|     Name       |  Type  |                    Usage                       |
| -------------- | ------ | ---------------------------------------------- |
| token\_type    | string | The token type                                 |
| expires\_in    | int    | The time the token is valid in seconds         |
| access\_token  | string | The access token                               |
| refresh\_token | string | The refresh token to request new access tokens |

### Refresh token

The refresh token can be used to obtain new access tokens. The primary use case is to avoid asking the user for credentials again when the **access\_token** expires.
You get the same response as if you sent the credentials again.

**Request body**

```json
    {
        "grant_type": "refresh_token",
        "client_id": "administration",
        "scopes": "write",
        "refresh_token": "def50200eae2708c871cd3b9b28b05053d59f5981a35674394bf4fc425e7a7b98b9ed1327c6d2317bbb97357ea244a0e014e9c797da074cb2f145588afa58eb3a04e25f511cc22a654b44f1ce694fea52bfd5790da5374fb0f6d343a636efc7d88c4336900e71597b5378f583bdc51006a5c523b3b7121d7ad0e34bf23a60cd794a8b206ac91606726f69399d5d1f24ffb21527b9d502c3f363b8f2acd9c44feaf3eb0bdbef4c7f08300b409fe61aef9853cd6d05bb958cdc936bb29101d01c4378f8e1253196d694a024fe67663db0211dedb2737502f39ce068da1771d1c53715378d06105b2d477c68a0aaa571994e2b75860cfcb46f758a383222cbd59c3d0ce279b60370eab0dcd6518da3bdae28266b06bc811c0d4b7182cb8925df4b329140dec0dc11632749c825d5dd3cd5ad37a57947fe7d42e0ddc712f8dc07d3e09cea958eb7236a269b505b4ce00a3548366cd82664357a4001e9f709d673adae8c5780b11484ef80a481ce49ca3ad6598f589f8a306332c8105b641c2260493e38f0f3431847fef670f790f30f6457654daa9378c6081e5f1cd206c95c4"
    }
```

### Full example

The following example shows how to retrieve a token by password and how to get a new token by refresh token.

```javascript
    const baseUrl = '{insert your url}';
    const data = {
        client_id: "administration",
        grant_type: "password",
        scopes: "write",
        username: "admin",
        password: "shopware"
    };
    const headers = { "Content-Type": "application/json" };
    const init = {
        method: 'POST',
        body: JSON.stringify(data),
        headers
    };
    
    fetch(`${baseUrl}/api/oauth/token`, init)
        .then((response) => response.json())
        .then((responseData) => {
            console.log('Token response', responseData);
    
            const refreshData = {
                grant_type: "refresh_token",
                client_id: "administration",
                scopes: "write",
                refresh_token: responseData.refresh_token
            };
            const refreshInit = {
                method: 'POST',
                body: JSON.stringify(refreshData),
                headers
            };
            fetch(`${baseUrl}/api/oauth/token`, refreshInit)
                .then((response) => response.json())
                .then((refreshResponse) => {
                    console.log('Refresh token reponse', refreshResponse);
                })
        });
```

## Client credential authentication

### Request body

```json
    {
        "client_id": "SWIABXZIEHRJVNJPZVNKUNI5EA",
        "client_secret": "REJzWnlLQmRCUVZ3YjA3c0hSWXBOdlRmZUdqRTltaDR3QWx4dFI",
        "grant_type": "client_credentials"
    }
```

### Response body

```json
    {
        "token_type": "Bearer",
        "expires_in": 3600,
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6Ijg3MmQzNGE5MWY5NGUwODU1YjdjNGJhNWQ2NTcwMzEzYjQ3NTg2OGZmMzdlNTBiZWY2NTJmODc1OTAxODI1MmUwZWQ3Y2M5NDljMjQwZDVlIn0.eyJhdWQiOiJhZG1pbmlzdHJhdGlvbiIsImp0aSI6Ijg3MmQzNGE5MWY5NGUwODU1YjdjNGJhNWQ2NTcwMzEzYjQ3NTg2OGZmMzdlNTBiZWY2NTJmODc1OTAxODI1MmUwZWQ3Y2M5NDljMjQwZDVlIiwiaWF0IjoxNTM2ODI3OTY3LCJuYmYiOjE1MzY4Mjc5NjcsImV4cCI6MTUzNjgzMTU2Nywic3ViIjoiNjM5YWJkZWIwMGMyNDFmOTgwYzllZWY0NDVjOWIzOWIiLCJzY29wZXMiOlsid3JpdGUiLCJ3cml0ZSJdfQ.U05dmw300bDbW6onTwIkdewFztbcdTSucnh-13s-tPZkOHh5liGzZ2enstIfY4qaucY1okFjBXRxVnFVXD21YYwY0SsWMufxwdrfDONVcGKXQlbD7zOZw8-WYNuAsAFkKuuCFSkrzXxmBHkchDUWI4o0s3RzFCnX_N-GxBnB6U-oPjjXhqASG3c1OZY9PRuEHQspi2mA2TDouSTnpS8rvM90vhnZR1UNz5HDEKQuG6MyNDfPmvc5n6mjItIQDgQC-jGB9BEW5ISP2ma4CKtvTL04CDlnld2s_O0JLqx3FbKIkT-3wD4sRPtw8bHdEOYWfBeroxRYJiFPiJ1cx_32wA"
    }
```
