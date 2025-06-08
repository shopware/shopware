---
title: Deprecated exceptions and properties due to PHPStan update
issue: NEXT-37561
author: Michael Telgmann
author_github: @mitelg
---
# Core
* Deprecated properties of `\Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeEntity`. They will be typed natively in v6.7.0.0.
* Deprecated properties of `\Shopware\Core\Framework\DataAbstractionLayer\Entity`. They will be typed natively in v6.7.0.0.
* Deprecated properties of `\Shopware\Core\Framework\DataAbstractionLayer\Field\FkField`. They will be typed natively in v6.7.0.0.
* Deprecated properties of `\Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField`. They will be typed natively in v6.7.0.0.
* Deprecated exception `\Shopware\Core\Framework\Api\Exception\UnsupportedEncoderInputException`. It will be removed in v6.7.0.0. Use `\Shopware\Core\Framework\Api\ApiException::unsupportedEncoderInput` instead.
* Deprecated exception `\Shopware\Core\Framework\DataAbstractionLayer\Exception\CanNotFindParentStorageFieldException`. It will be removed in v6.7.0.0. Use `\Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException::cannotFindParentStorageField` instead.
* Deprecated exception `\Shopware\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException`. It will be removed in v6.7.0.0. Use `\Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException::internalFieldAccessNotAllowed` instead.
* Deprecated exception `\Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidParentAssociationException`. It will be removed in v6.7.0.0. Use `\Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException::invalidParentAssociation` instead.
* Deprecated exception `\Shopware\Core\Framework\DataAbstractionLayer\Exception\ParentFieldNotFoundException`. It will be removed in v6.7.0.0. Use `\Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException::parentFieldNotFound` instead.
* Deprecated exception `\Shopware\Core\Framework\DataAbstractionLayer\Exception\PrimaryKeyNotProvidedException`. It will be removed in v6.7.0.0. Use `\Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException::primaryKeyNotProvided` instead.
* Deprecated method `\Shopware\Core\Framework\DataAbstractionLayer\Entity::__get`. It will throw a `\Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException` instead of a `\Shopware\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException` in v6.7.0.0.
* Deprecated method `\Shopware\Core\Framework\DataAbstractionLayer\Entity::get`. It will throw a `\Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException` instead of a `\Shopware\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException` in v6.7.0.0.
* Deprecated method `\Shopware\Core\Framework\DataAbstractionLayer\Entity::checkIfPropertyAccessIsAllowed`. It will throw a `\Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException` instead of a `\Shopware\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException` in v6.7.0.0.
* Deprecated method `\Shopware\Core\Framework\DataAbstractionLayer\Entity::get`. It will throw a `\Shopware\Core\Framework\DataAbstractionLayer\Exception\PropertyNotFoundException` instead of a `\InvalidArgumentException` in v6.7.0.0.
___
# Upgrade Information
## Native typehints of properties
The properties of the following classes will be typed natively in v6.7.0.0.
If you have extended from those classes and overwritten the properties, you can already set the correct type.
* `\Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeEntity`
* `\Shopware\Core\Framework\DataAbstractionLayer\Entity`
* `\Shopware\Core\Framework\DataAbstractionLayer\Field\FkField`
* `\Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField`
## Deprecated exceptions
The following exceptions were deprecated and will be removed in v6.7.0.0.
You can already catch the replacement exceptions additionally to the deprecated ones.
* `\Shopware\Core\Framework\Api\Exception\UnsupportedEncoderInputException`. Also catch `\Shopware\Core\Framework\Api\ApiException`.
* `\Shopware\Core\Framework\DataAbstractionLayer\Exception\CanNotFindParentStorageFieldException`. Also catch `\Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException`.
* `\Shopware\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException`. Also catch `\Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException`.
* `\Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidParentAssociationException`. Also catch `\Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException`.
* `\Shopware\Core\Framework\DataAbstractionLayer\Exception\ParentFieldNotFoundException`. Also catch `\Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException`.
* `\Shopware\Core\Framework\DataAbstractionLayer\Exception\PrimaryKeyNotProvidedException`. Also catch `\Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException`.
## Deprecated methods
The following methods of the `\Shopware\Core\Framework\DataAbstractionLayer\Entity` class were deprecated and will throw different exceptions in v6.7.0.0.
You can already catch the replacement exceptions additionally to the deprecated ones.
* `\Shopware\Core\Framework\DataAbstractionLayer\Entity::__get`. Also catch `\Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException` in addition to `\Shopware\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException`.
* `\Shopware\Core\Framework\DataAbstractionLayer\Entity::get`. Also catch `\Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException` in addition to `\Shopware\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException`.
* `\Shopware\Core\Framework\DataAbstractionLayer\Entity::checkIfPropertyAccessIsAllowed`. Also catch `\Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException` in addition to `\Shopware\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException`.
* `\Shopware\Core\Framework\DataAbstractionLayer\Entity::get`. Also catch `\Shopware\Core\Framework\DataAbstractionLayer\Exception\PropertyNotFoundException` in addition to `\InvalidArgumentException`.
___
# Next Major Version Changes
## Removal of deprecated exceptions
The following exceptions were removed:
* `\Shopware\Core\Framework\Api\Exception\UnsupportedEncoderInputException`
* `\Shopware\Core\Framework\DataAbstractionLayer\Exception\CanNotFindParentStorageFieldException`
* `\Shopware\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException`
* `\Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidParentAssociationException`
* `\Shopware\Core\Framework\DataAbstractionLayer\Exception\ParentFieldNotFoundException`
* `\Shopware\Core\Framework\DataAbstractionLayer\Exception\PrimaryKeyNotProvidedException`
## Entity class throws different exceptions
The following methods of the `\Shopware\Core\Framework\DataAbstractionLayer\Entity` class are now throwing different exceptions:
* `\Shopware\Core\Framework\DataAbstractionLayer\Entity::__get` now throws a `\Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException` instead of a `\Shopware\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException`.
* `\Shopware\Core\Framework\DataAbstractionLayer\Entity::get` now throws a `\Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException` instead of a `\Shopware\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException`.
* `\Shopware\Core\Framework\DataAbstractionLayer\Entity::checkIfPropertyAccessIsAllowed` now throws a `\Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException` instead of a `\Shopware\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException`.
* `\Shopware\Core\Framework\DataAbstractionLayer\Entity::get` now throws a `\Shopware\Core\Framework\DataAbstractionLayer\Exception\PropertyNotFoundException` instead of a `\InvalidArgumentException`.
