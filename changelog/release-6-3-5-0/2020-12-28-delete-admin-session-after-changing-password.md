---
title: Delete admin session after changing password
issue: NEXT-9007
---
# Core
* Added a new timestamp column `last_updated_password_at` in `user` table.
* Added a new method `\Shopware\Core\Framework\Api\EventListener\Authentication\UserCredentialsChangedSubscriber::updateLastUpdatedPasswordTimestamp` to save the last time a user password is updated.
* Added a new class `\Shopware\Core\Framework\Api\OAuth\BearerTokenValidator` that decorates `\League\OAuth2\Server\AuthorizationValidators\BearerTokenValidator` to validate an access token's issued at.
