---
title: Adjust catch block inside saveWithSync method
issue: NEXT-23580
---
# Administration
* Changed method `saveWithSync` in src/Administration/Resources/app/administration/src/core/data/repository.data.ts inside the error handling part to prevent runtime errors by using optional chaining operator
