---
title: Checkout Error with a combination of Promotion and Rule Builder
author: d.popovic
author_email: darko.popovic@ditegra.de
author_github: dpopov00
---
# Core
* Changed `src\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceDefinitionFieldSerializer.php`
* Rule 'Item with price/list price percentage ratio||Position mit prozentualen Preis/Streichpreis Verhältnis' has an option "Is empty" and in that case "amount" field is null.
* Inside PriceDefinitionFieldSerializer.php '$violationList->addAll($this->validateConsistence($basePath, $validations, $data));', both fields (operator and amount) are sent to the validateConsistence() and because the amount is null, checkout fails
* Fix to unset the amount field (if the operator is 'empty') before it is passed to validateConsistence()