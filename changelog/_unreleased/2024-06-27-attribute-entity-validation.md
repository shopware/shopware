---
title: Add Attribute Entity validation
issue: NEXT-00000
author: Raffaele Carelle
author_email: raffaele.carelle@gmail.com
author_github: raffaelecarelle
---
# Core
* Add [AttributeConstraintAwareInterface.php](../../src/Core/Framework/DataAbstractionLayer/AttributeConstraintAwareInterface.php)
* [AttributeEntityDefinition.php](../../src/Core/Framework/DataAbstractionLayer/AttributeEntityDefinition.php) now implements added AttributeConstraintAwareInterface
* [EntityWriter.php](../../src/Core/Framework/DataAbstractionLayer/Write/EntityWriter.php) now collects for each field the constraints defined with Attributes and validate data with Shopware [DataValidator.php](../../src/Core/Framework/Validation/DataValidator.php)
