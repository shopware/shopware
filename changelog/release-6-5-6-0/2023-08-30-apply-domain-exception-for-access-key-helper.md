---
title: Apply domain exception for Access key helper
issue: NEXT-30098
---
# Core
* Added 2 new exception methods `invalidAccessKey` and `invalidAccessKeyIdentifier` in `Core\Framework\Ap\ApiException` 
* Added an alternative exception by throwing `ApiException::invalidAccessKey()` in `getOrigin` method of `Core\Framework\Api\Util\AccessKeyHelper` 
* Added an alternative exception by throwing `ApiException::invalidAccessKeyIdentifier()` in `getIdentifier` method of `Core\Framework\Api\Util\AccessKeyHelper` 
