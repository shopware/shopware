---
title: Unify hmac signature AppSystem client
issue: NEXT-15806
---
# Core
* Added Guzzle middleware for unify sign and verify AppSystem API requests.
___
# Upgrade information
Every request of App System now can automatically sign or verify when you use Guzzle client with a define Option Request 

Usage:

```
try {
    $result = $client->post('https://example.com', [
       app_request_type => [ 
            app_secret: string // this is required
            validated_response: true/false
       ]
    ]);
} catch (ClientException $e) {
    print $e->getMessage();
    $response = $e->getResponse();
}
```

