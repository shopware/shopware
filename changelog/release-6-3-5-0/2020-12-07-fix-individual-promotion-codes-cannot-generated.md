---
title: Fix individual promotion codes cannot generate
issue: NEXT-12139
---
# Administration
* Changed method `openModalIndividualCodes` in `src\Administration\Resources\app\administration\src\module\sw-promotion\component\sw-promotion-code-form\index.js`
* Changed method `onGenerateClick` in `src\Administration\Resources\app\administration\src\module\sw-promotion\component\sw-promotion-individualcodes\index.js` to handle `individualCodePattern` invalid
* Changed method `createdComponent` in `src\Administration\Resources\app\administration\src\module\sw-promotion\page\sw-promotion-detail\index.js`
* Changed function `createCodes` in `src\Administration\Resources\app\administration\src\module\sw-promotion\service\individual-code-generator.service.js` to fix loop when `pattern` invalid, ex: my-code
* Changed function `getRandomCharacter` in `src\Administration\Resources\app\administration\src\module\sw-promotion\service\code-generator.service.js`
* Changed function `getRandomNumber` in `src\Administration\Resources\app\administration\src\module\sw-promotion\service\code-generator.service.js`
* Added function `getDigit` in `src\Administration\Resources\app\administration\src\module\sw-promotion\service\code-generator.service.js`
* Added function `getCharacters` in `src\Administration\Resources\app\administration\src\module\sw-promotion\service\code-generator.service.js`
