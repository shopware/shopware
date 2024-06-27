---
title: Add Attribute Entity validation
issue: NEXT-00000
author: Raffaele Carelle
author_email: raffaele.carelle@gmail.com
author_github: raffaelecarelle
---
# Core
* Added [AttributeConstraintAwareInterface.php](../../src/Core/Framework/DataAbstractionLayer/AttributeConstraintAwareInterface.php)
* Changed [AttributeEntityDefinition.php](../../src/Core/Framework/DataAbstractionLayer/AttributeEntityDefinition.php) that now implements added AttributeConstraintAwareInterface
* Changed [EntityWriter.php](../../src/Core/Framework/DataAbstractionLayer/Write/EntityWriter.php) that now collects for each field the constraints defined with Attributes and validate data with Shopware [DataValidator.php](../../src/Core/Framework/Validation/DataValidator.php)
