# 2022-01-06 - Allow apps to define custom api endpoints

## Context
Apps should be allowed to provide their own API and Store-API endpoints where they can execute different logics, that deviates from the automatic entity API.

## Decision
We implement two new endpoints: 
- `/api/script/{hook}`.
- `/store-api/script/{hook}`.

The `{hook}` parameter is used as the script hook name and prefixed with the url prefix (`api-`, `store-api`).

This hook is then executed and apps have the possibility to load or even write data in the scripts.

The following data is given to the script:
* [array] request.request.all
* [context/sales channel context] context
* [ScriptResponse] response

The response allows to set a status code as well as a response body. Further HTTP access is disabled for security reasons.
