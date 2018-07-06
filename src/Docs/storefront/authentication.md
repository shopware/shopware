- [Storefront Authentication](#storefront-authentication)
  * [Request](#request)
  * [Default Access](#default-access)
  * [Access Token](#access-token)
  * [Examples](#examples)
    + [PHP](#php)
    + [Curl](#curl)
    + [Python](#python)
    + [Java](#java)
    + [Javascript](#javascript)
    + [jQuery](#jquery)
    + [NodeJS Native](#nodejs-native)
    + [Go](#go)

# Storefront Authentication
To be able to send a request against the Storefront API, an oAuth authentication must first be made via the following route:

## Request
```
POST /storefront-api/oauth/token HTTP/1.1
Host: shopware.development
Content-Type: application/json
Cache-Control: no-cache

{
     "client_id": "b1FTZGVSd2xoSmRBWnhlZldnOVhEZTBXandRb2M0VXA",
     "client_secret": "d2J0a0RZdUxCRkRDQmpOQmlINk5NZEQyS3gyNUQ5ZzJHSThQQU5BYm1VQmpSVU12VjIxUg",
     "grant_type": "client_credentials"
}
```

## Default Access
The corresponding client_id and client_secret are generated when a touchpoint is initialized. In the current state of development, the initialization of the environment automatically generates a touchpoint for accessing the Storefront API:

```
(9/11) Starting
> php bin/console touchpoint:create --tenant-id=ffffffffffffffffffffffffffffffff --id=ffffffffffffffffffffffffffffffff

      [OK] Touchpoint has been created successfully.

      Access tokens:
     +-------------------+------------------------------------------------------------------------+
     | Key               | Value                                                                  |
     +-------------------+------------------------------------------------------------------------+
     | Access key        | b1FTZGVSd2xoSmRBWnhlZldnOVhEZTBXandRb2M0VXA                            |
     | Secret access key | d2J0a0RZdUxCRkRDQmpOQmlINk5NZEQyS3gyNUQ5ZzJHSThQQU5BYm1VQmpSVU12VjIxUg |
     +-------------------+------------------------------------------------------------------------+
```

## Access Token
If the authentication was successful, the response includes an access token which can be used for authentication for further request: 
```json
{
    "token_type": "Bearer",
    "expires_in": 3600,
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImU4YjRlM2FlYTZiY2YyNGYzYzZiZjgxNTNhNzE2N2E2OTcyMzdmMjhjYjJjYmFjZDE5OTc2ZmNjNWEwZjI2YzlmYTNjNjhkMTlkNTU1YTljIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImU4YjRlM2FlYTZiY2YyNGYzYzZiZjgxNTNhNzE2N2E2OTcyMzdmMjhjYjJjYmFjZDE5OTc2ZmNjNWEwZjI2YzlmYTNjNjhkMTlkNTU1YTljIiwiaWF0IjoxNTMwODYxMTY2LCJuYmYiOjE1MzA4NjExNjYsImV4cCI6MTUzMDg2NDc2Niwic3ViIjoiIiwic2NvcGVzIjpbXX0.eHKEsDImYA9wUvVVIsd1S__ZIKXkLHtXbFeV62ZnZ6dLJQSH-7ZxbgoHY76FCO_1WcuGK2zYZVLfFFnaGVGJUQRjkQ1FqnTU4SPto-NEGd_Q1KdxlMacrXY5MtpYhyb1S0t9xGa2acjaqu6fZ4xFcugcfrlxhBM3KDdR8BFmGT2OkOY13XYwLN2K5OvYN_zav2uaisdYKUZKQqhSC-FPjg4ErHH7rIS7uDsZYvExzc8e84_DuQ3Lf4njg6BrxFHL6J-rko48k_Gf3GttKjnikkkLFxdYvglvR9ucHYeZkgcWUbsO9lpJp_7iFYTe4OhGRkINIppdnEWJgwq7Sh0RqQ"
}
```

## Examples

### PHP
```php
<?php

$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => "http://shopware.development/storefront-api/oauth/token",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => json_encode(array(
        "client_id" => "b1FTZGVSd2xoSmRBWnhlZldnOVhEZTBXandRb2M0VXA",
        "client_secret" => "d2J0a0RZdUxCRkRDQmpOQmlINk5NZEQyS3gyNUQ5ZzJHSThQQU5BYm1VQmpSVU12VjIxUg",
        "grant_type" => "client_credentials"
    )),
    CURLOPT_HTTPHEADER => array(
        "Cache-Control: no-cache",
        "Content-Type: application/json"
    ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    echo "cURL Error #:" . $err;
} else {
    echo $response;
}
```

### Curl
```
curl -X POST \
  http://shopware.development/storefront-api/oauth/token \
  -H 'Cache-Control: no-cache' \
  -H 'Content-Type: application/json' \
  -d '{
     "client_id": "b1FTZGVSd2xoSmRBWnhlZldnOVhEZTBXandRb2M0VXA",
     "client_secret": "d2J0a0RZdUxCRkRDQmpOQmlINk5NZEQyS3gyNUQ5ZzJHSThQQU5BYm1VQmpSVU12VjIxUg",
     "grant_type": "client_credentials"
}'
```

### Python
```python
import http.client

conn = http.client.HTTPConnection("shopware,development")

payload = "{ \"client_id\": \"b1FTZGVSd2xoSmRBWnhlZldnOVhEZTBXandRb2M0VXA\", \"client_secret\": \"d2J0a0RZdUxCRkRDQmpOQmlINk5NZEQyS3gyNUQ5ZzJHSThQQU5BYm1VQmpSVU12VjIxUg\", \"grant_type\": \"client_credentials\"\n}"

headers = {
    'Content-Type': "application/json",
    'Cache-Control': "no-cache"
}

conn.request("POST", "storefront-api,oauth,token", payload, headers)

res = conn.getresponse()
data = res.read()

print(data.decode("utf-8"))
```

### Java
```
OkHttpClient client = new OkHttpClient();

MediaType mediaType = MediaType.parse("application/json");
RequestBody body = RequestBody.create(mediaType, "{\"client_id\": \"b1FTZGVSd2xoSmRBWnhlZldnOVhEZTBXandRb2M0VXA\",\"client_secret\": \"d2J0a0RZdUxCRkRDQmpOQmlINk5NZEQyS3gyNUQ5ZzJHSThQQU5BYm1VQmpSVU12VjIxUg\",\"grant_type\": \"client_credentials\"\n}");
Request request = new Request.Builder()
  .url("http://shopware.development/storefront-api/oauth/token")
  .post(body)
  .addHeader("Content-Type", "application/json")
  .addHeader("Cache-Control", "no-cache")
  .addHeader("Postman-Token", "cf1b7a2b-9669-4d3f-918a-622001ff6c45")
  .build();

Response response = client.newCall(request).execute();
```

### Javascript
```javascript
var data = JSON.stringify({
  "client_id": "b1FTZGVSd2xoSmRBWnhlZldnOVhEZTBXandRb2M0VXA",
  "client_secret": "d2J0a0RZdUxCRkRDQmpOQmlINk5NZEQyS3gyNUQ5ZzJHSThQQU5BYm1VQmpSVU12VjIxUg",
  "grant_type": "client_credentials"
});

var xhr = new XMLHttpRequest();
xhr.withCredentials = true;

xhr.addEventListener("readystatechange", function () {
  if (this.readyState === 4) {
    console.log(this.responseText);
  }
});

xhr.open("POST", "http://shopware.development/storefront-api/oauth/token");
xhr.setRequestHeader("Content-Type", "application/json");
xhr.setRequestHeader("Cache-Control", "no-cache");

xhr.send(data);
```


### jQuery
```javascript
var settings = {
  "async": true,
  "crossDomain": true,
  "url": "http://shopware.development/storefront-api/oauth/token",
  "method": "POST",
  "headers": {
    "Content-Type": "application/json",
    "Cache-Control": "no-cache"
  },
  "processData": false,
  "data": {
    "client_id": "b1FTZGVSd2xoSmRBWnhlZldnOVhEZTBXandRb2M0VXA",
    "client_secret": "d2J0a0RZdUxCRkRDQmpOQmlINk5NZEQyS3gyNUQ5ZzJHSThQQU5BYm1VQmpSVU12VjIxUg",
    "grant_type": "client_credentials"
  }
}

$.ajax(settings).done(function (response) {
  console.log(response);
});

$.ajax(settings).done(function (response) {
  console.log(response);
});
```

### NodeJS Native
```javascript
var http = require("http");

var options = {
  "method": "POST",
  "hostname": [
    "shopware",
    "development"
  ],
  "path": [
    "storefront-api",
    "oauth",
    "token"
  ],
  "headers": {
    "Content-Type": "application/json",
    "Cache-Control": "no-cache"
  }
};

var req = http.request(options, function (res) {
  var chunks = [];

  res.on("data", function (chunk) {
    chunks.push(chunk);
  });

  res.on("end", function () {
    var body = Buffer.concat(chunks);
    console.log(body.toString());
  });
});

req.write(JSON.stringify({ client_id: 'b1FTZGVSd2xoSmRBWnhlZldnOVhEZTBXandRb2M0VXA',
  client_secret: 'd2J0a0RZdUxCRkRDQmpOQmlINk5NZEQyS3gyNUQ5ZzJHSThQQU5BYm1VQmpSVU12VjIxUg',
  grant_type: 'client_credentials' }));
req.end();
```

### Go
```go
package main

import (
     "fmt"
     "strings"
     "net/http"
     "io/ioutil"
)

func main() {

     url := "http://shopware.development/storefront-api/oauth/token"

     payload := strings.NewReader("{ \"client_id\": \"b1FTZGVSd2xoSmRBWnhlZldnOVhEZTBXandRb2M0VXA\", \"client_secret\": \"d2J0a0RZdUxCRkRDQmpOQmlINk5NZEQyS3gyNUQ5ZzJHSThQQU5BYm1VQmpSVU12VjIxUg\", \"grant_type\": \"client_credentials\"\n}")

     req, _ := http.NewRequest("POST", url, payload)

     req.Header.Add("Content-Type", "application/json")
     req.Header.Add("Cache-Control", "no-cache")
     req.Header.Add("Postman-Token", "aa7f2672-b02f-4d80-b444-a9c1821bb205")

     res, _ := http.DefaultClient.Do(req)

     defer res.Body.Close()
     body, _ := ioutil.ReadAll(res.Body)

     fmt.Println(res)
     fmt.Println(string(body))

}
```