---
title: New API to allow users to read and update their own configuration
issue: NEXT-17591
flag: FEATURE_NEXT_6040
---
# Administration
*  Added new controller `\Shopware\Administration\Controller\UserConfigController`
___
# API
* Added endpoint `api/_info/config-me` with method `GET` and `POST` into `Shopware\Core\Framework\Api\Controller\UserController`
___
# Upgrade Information
## Get a list of user configuration from current logged-in user
### Before
Request:
```
[POST] /api/search/user-config

Body
{
   "page":1,
   "limit":25,
   "filter":[
      {
         "type":"equals",
         "field":"key",
         "value":"search.preferences"
      },
      {
         "type":"equals",
         "field":"userId",
         "value":"71d4deac6d7b410c982d1f1883960e25"
      }
   ],
   "total-count-mode":1
}
```
Response
```json
{
    "data": [
        {
            "id": "c5bf30ddca1449a480a161b1130cf640",
            "type": "user_config",
            "attributes": {
                "userId": "71d4deac6d7b410c982d1f1883960e25",
                "key": "search.preferences",
                "value": [
                    {
                        "order": {
                            "tags": {
                                "name": {
                                    "_score": 500,
                                    "_searchable": false
                                }
                            }
                        }
                    }
                ]
            }
        }
    ]
}
```
### After
Request:
```
[GET] /api/_info/config-me?keys[]=key1&keys[]=keys2
```
Response

Case 1: The key exists

Status code: 200
```json
{
    "data": {
        "key1" : {
            "order": {
                "tags": {
                    "name": {
                        "_score": 500,
                        "_searchable": false
                    }
                }
            }
        },
        "key2" : {
            "product": {
                "tags": {
                    "name": {
                        "_score": 500,
                        "_searchable": false
                    }
                }
            }
        }
    }
}
```
Case 2: The key does not exist

Status code: 404

Case 3: Without sending `keys` parameter, return all configurations of current logged-in user

Request:
```
[GET] /api/_info/config-me
```
## Mass Update/Insert user configuration for logged-in user
### Before
Request:
```
[POST] /api/user-config

Body

{
    "id": "43d2ba68b65e4154a38d9aa2501162e4"
    "key": "grid.setting.sw-order-list"
    "userId": "71d4deac6d7b410c982d1f1883960e25",
    "value": {},
}
```
```
[PATCH] /api/user-config/43d2ba68b65e4154a38d9aa2501162e4

Body

{
    "id": "43d2ba68b65e4154a38d9aa2501162e4"
    "value": {},
}
```

### After
Post an array of the configuration, which key of array is the key of user_config, and value of the array is the value you want to create or update. If the key exists, it will do the update action. Otherwise, it will create a new one with a given key and value

Request:
```
[POST] /api/_info/config-me

Body

{
    "key1": "value1",
    "key2": "value2" 
}
```

Response

Status code: 204
