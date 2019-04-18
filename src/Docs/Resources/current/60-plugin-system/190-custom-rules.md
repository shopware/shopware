[titleEn]: <>(Custom Rules)
[titleDe]: <>(Custom Rules)
[wikiUrl]: <>(../plugin-system/custom-rules?category=shopware-platform-en/plugin-system)

Before starting you should read 
[Creating a component](../10-administration/20-create-a-component.md) 
and you should know how to 
[create a plugin](../10-administration/01-administration-start-development.md#create-your-first-plugin).

## Create a rule in PHP

Create a class, let it extend the abstract 'Rule' class and implement the missing methods.

```php
<?php declare(strict_types=1);

namespace SwagCustomRule\Core\Rule;

use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;

class ExampleRule extends Rule
{
    public function getName(): string
    {
        ...
    }

    public function match(RuleScope $scope): bool
    {
        ...
    }

    public function getConstraints(): array
    {
        ...
    }
}
```

Tag this new rule as a "shopware.rule.definition" in the DI-Container.

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">
    <services>
        ...
        <service id="SwagCustomRule\Core\Rule\ExampleRule">
            <tag name="shopware.rule.definition"/>
        </service>
        ...
    </services>
</container>
```

Your PHP Code is now set up and ready to go. Now you need your administration to get to know this rule.

## Let the administration know your rule

After registering your rule you now need to let the administration know how to deal with it. 
Therefore you're going to add a component to your existing module.

```javascript
import { Component } from 'src/core/shopware';
import template from './swag-example.html.twig';

Component.extend('swag-example', 'sw-condition-base', {
    template,
    ...
});
```

Your component extends the 'sw-condition-base' component and will replace the 'sw_condition_fields' block in the twig template.

```twig
{% block sw_condition_base_fields %}
    ...
{% endblock %}
```

Last but not least you need to call the ruleServiceProviderDecorator of the administration and tell it to 
add your rule to the already existing ones.

```javascript
import { Application } from 'src/core/shopware';
import '../core/component/swag-example';

Application.addServiceProviderDecorator('ruleConditionDataProviderService', (ruleConditionService) => {
    ...
    ruleConditionService.addCondition('swagExample', {
        component: 'swag-example',
        label: 'swag-custom-rule.condition.example'
    });
    ...
    return ruleConditionService;
});
```

Your component should now be available to be selected.

## Download
Here you can *Download Link Here* the Plugin.
