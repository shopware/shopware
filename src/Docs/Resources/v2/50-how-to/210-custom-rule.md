[titleEn]: <>(Register a custom rule via plugin)
[metaDescriptionEn]: <>(Shopware 6 comes with a new rule system. This HowTo comes with an example, which integrates a fully working custom rule.)
[hash]: <>(article:how_to_custom_rule)

## Overview

Shopware 6 comes with a new rule system.
If you're wondering, how you can create a new custom rule with your plugin, make sure to read this HowTo.

This example will introduce a new rule, which checks if there's currently a lunar eclipse or not.
The shop administrator is then able to react on a lunar eclipse with special prices or dispatch methods.

## Setup

This HowTo **does not** explain how you can create a new plugin for Shopware 6.
Head over to our [Plugin quick start guide](./../2-internals/4-plugins/010-plugin-quick-start.md) to
learn creating a plugin at first.

Creating a custom rule requires you to implement both Backend (PHP) code, as well as an UI for the administration.
Let's start with the PHP part first, which basically handles the main logic of your rule.
Afterwards there'll be an example to actually show your new rule in the administration.

## Creating your rule in PHP

First of all you need a new Rule class, a good naming for this example would be `LunarEclipseRule`.
It has to extend from the abstract class `Shopware\Core\Framework\Rule\Rule`.
In this example, it will be placed in the directory `<plugin root>/src/Core/Rule`.

```php
<?php declare(strict_types=1);

namespace Swag\CustomRule\Core\Rule;

use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Type;

class LunarEclipseRule extends Rule
{
    /**
     * @var bool
     */
    protected $isLunarEclipse;

    public function __construct()
    {
        // Will be overwritten at runtime. Reflects the expected value.
        $this->isLunarEclipse = false;
    }

    public function getName(): string
    {
        return 'lunar_eclipse';
    }

    public function match(RuleScope $scope): bool
    {
        // Not implemented in this example
        $isCurrentlyLunarEclipse = $this->isCurrentlyLunarEclipse();

        // Checks if the shop administrator set the rule to "Lunar eclipse => Yes"
        if ($this->isLunarEclipse) {
            // Shop administrator wants the rule to match if there's currently a lunar eclipse.
            return $isCurrentlyLunarEclipse;
        }

        // Shop administrator wants the rule to match if there's currently NOT a lunar eclipse.
        return !$isCurrentlyLunarEclipse;
    }

    public function getConstraints(): array
    {
        return [
            'isLunarEclipse' => [ new Type('bool') ]
        ];
    }
}
```

As you might have noticed, there's already several methods implemented:
- `__constructor`: This only defines the default **expected** value. This is overwritten at runtime with the actual value, that the shop administrator set in the administration
- `getName`: Just return a unique technical name for your rule here
- `match`: This is where you're actually checking, if the rule applies. Return a boolean here, depending on whether or not the rule actually applies.
- `getConstraints`: This method returns an array of the possible fields and its types. You could also return the `NotBlank` class here, to require this field.

Time to register it in the DI container via the `services.xml` of your plugin.
If your plugin does not have a `services.xml` file yet, make sure to read [here](./../2-internals/4-plugins/010-plugin-quick-start.md#The services.xml) to understand how it can be created in the first place.

Your rule has to be defined as a service together with the tag `shopware.rule.definition`:
```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Swag\CustomRule\Core\Rule\LunarEclipseRule">
            <tag name="shopware.rule.definition"/>
        </service>
    </services>
</container>
```

And that's it, your rule is done already, at least in the Backend.

In the next step, you'll find a brief example of how the administration template could look like.

## Showing your rule in the administration

You want to let the administration know of your new rule now.
To achieve this, you have to call the `addCondition` method of the [RuleConditionService](https://github.com/shopware/platform/blob/master/src/Administration/Resources/app/administration/src/app/service/rule-condition.service.js), by decorating the said service.

For this purpose, you create a new directory called `<plugin root>/src/Resources/app/administration/src/decorator` and in there a new file.
The file's name is up to you, in this example it will be called `rule-condition-service-decoration.js`.

```js
import '../core/component/swag-lunar-eclipse';

Shopware.Application.addServiceProviderDecorator('ruleConditionDataProviderService', (ruleConditionService) => {
    ruleConditionService.addCondition('lunar_eclipse', {
        component: 'swag-lunar-eclipse',
        label: 'Is lunar eclipse today',
        scopes: ['global']
    });

    return ruleConditionService;
});

```

As you can see, this is decorating the `RuleConditionService` by using its name `ruleConditionDataProviderService`, which is defined [here](https://github.com/shopware/platform/blob/master/src/Administration/Resources/app/administration/src/app/main.js#L47).
The decoration then adds a new condition called 'lunar_eclipse'. Make sure to match the name you've used in the `getName` method in PHP.
It comes with a custom component `swag-lunar-eclipse`, which you have to create later on, as well as an label.
Also note the second line, which already imports your not yet existing component.

But this code is not executed yet, because it was never included or executed.

Your main entry point for this purpose is your plugin's `main.js` file.
It has to be placed into the `<plugin root>/src/Resources/app/administration/src` directory in order to be automatically found by Shopware 6.

In there you'll simply have to import the decoration file mentioned above:

```js
import './src/decorator/rule-condition-service-decoration';
```

### Custom rule component

While you've registered your rule to the administration now, you're still lacking the actual component `swag-lunar-eclipse`.
As previously mentioned, you've already defined a path for it in your service decoration: `core/component/swag-lunar-eclipse`.
Thus, create the following directory `<plugin root>/src/Resources/app/administration/src/core/component/swag-lunar-eclipse`.

Each component has to come with a file called `index.js`, defining your new component.
Here's an example of what this component could look like, with an explanation coming afterwards:

```js
import LocalStore from 'src/core/data/LocalStore';
import template from './swag-lunar-eclipse.html.twig';

Shopware.Component.extend('swag-lunar-eclipse', 'sw-condition-base', {
    template,

    computed: {
        fieldNames() {
            return ['isLunarEclipse'];
        },
        defaultValues() {
           return {
               isLunarEclipse: true
           };
        },
        selectValues() {
            const values = [
                {
                    label: this.$tc('global.sw-condition.condition.yes'),
                    value: 'true'
                },
                {
                    label: this.$tc('global.sw-condition.condition.no'),
                    value: 'false'
                }
            ];

            return new LocalStore(values, 'value');
        },
    },

    data() {
        return {
            isLunarEclipse: this.condition.value.isLunarEclipse !== undefined ? String(this.condition.value.isLunarEclipse) : String(true)
        };
    },
    
    watch: {
        isLunarEclipse: {
            handler(newValue) {
                this.condition.value.isLunarEclipse = newValue === String(true);
            }
        }
    },
});
```

So, first of all your component is named `swag-lunar-eclipse`, just as mentioned in previous code.
It has to extend from the `sw-condition-base` component and has to bring a custom template, which will be explained in the next step.

Let's have a look at each property and method.

The only required computed property is `fieldNames`, which defines the available fields in your custom rule.
Next is the method `defaultValues`, which is pretty much self-explaining - it simply defines the default values for the available fields.

The last computed property is `selectValues`, which returns a Store containing the values "true" and "false".
Those will be used in the template later on, as they will be the selectable options for the shop administrator.
Do not get confused by the call `this.$tc('global.sw-condition.condition.yes')`, it's just loading a translation by its name, in this case "Yes" and "No".
*Note: When dealing with boolean values, make sure to always return strings here!*

The `data` method is supposed to return an object containing the available variables and its actual current value.
If the value was defined already, use it, otherwise return the default value again.
Also remember: If you're dealing with boolean values, make sure to return a string here.

The last method is the `watch` method, which, as the name suggests, watches the `isLunarEclipse` value and triggers once the value is changed.
Its main purpose is to convert the string back to an actual boolean then and setting it into the condition's value, which then
gets submitted.

### Custom rule administration template

Now let's have a look at the template.
Once again it was already defined and named in the previous code: `./swag-lunar-eclipse.html.twig`

Just create a new file with this name in the same directory.
All it has to do then, is to extend the twig block `sw_condition_base_fields`, which contains the actual field representation for the current rule.
In there you'll need a select box containing the previously configured "Yes" and "No" values.

Here's a working example of your template could look like:
```twig
{% block sw_condition_base_fields %}
    <sw-select name="lunar-eclipse"
        id="lunar-eclipse"
        itemValueKey="value"
        displayName="label"
        :store="selectValues"
        :required="true"
        v-model="isLunarEclipse"
        class="field--main">
    </sw-select>
{% endblock %}
```

It uses the previously created computed property `selectValues` as a store and the value is saved into the variable `isLunarEclipse`.

And that's it, your rule is now fully integrated.

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-custom-rule).
