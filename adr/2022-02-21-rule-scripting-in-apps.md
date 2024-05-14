---
title: Rule Scripting in apps
date: 2022-02-21
area: services-settings
tags: [rule, app-system, app-scripts]
---

## Context

Currently rule conditions need to be hard-coded both as PHP classes and in the administration as Vue components. We want to introduce the possibility for apps to provide their own custom rule conditions.

## Decision

To allow apps to define custom logic for their rule conditions we implement a generic script rule. As with app scripting we use Twig, as it brings a secure PHP sandbox and allows interacting directly with objects. The scripts will be saved in the database and fetched when building a rule's payload or validating a rule. 

For storing the scripts in the database we use a new entity `app_script_condition` which not only contains the script that is used for evaluating a condition, but also the constraints used in rule validation. These pre-defined constraints can be configured in the manifest of the app and are also used to render the fields for the parameters of a condition when used in the rule builder within the administration.

### `AppScriptConditionDefinition` and associations

```php
class AppScriptConditionDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'app_script_condition';

    // ...

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new TranslatedField('name'),
            (new BoolField('active', 'active'))->addFlags(new Required()),
            new StringField('group', 'group'),
            (new LongTextField('script', 'script'))->addFlags(new Required(), new AllowHtml(false)),
            new JsonField('constraints', 'constraints'),
            (new FkField('app_id', 'appId', AppDefinition::class))->addFlags(new Required()),
            (new OneToManyAssociationField('conditions', RuleConditionDefinition::class, 'script_id', 'id'))->addFlags(new SetNullOnDelete(), new ReverseInherited('script')),
        ]);
    }
}
```

```diff
// src/Core/Content/Rule/Aggregate/RuleCondition/RuleConditionDefinition.php 

            (new FkField('rule_id', 'ruleId', RuleDefinition::class))->addFlags(new Required()),
+           new FkField('script_id', 'scriptId', AppScriptConditionDefinition::class),
            // ...
            new ManyToOneAssociationField('rule', 'rule_id', RuleDefinition::class, 'id', false),
+           new ManyToOneAssociationField('script', 'script_id', AppScriptConditionDefinition::class, 'id', true),
```

### `ScriptRule` implementation

There will be a generic extension of `Rule` named `ScriptRule` which will be used for every condition added by apps.

It has properties for the `script` and the `constraints`, both of which will be set from the corresponding values of `app_script_condition` when the rule's payload is indexed.

The constraints will be used for the validation of the condition and the script is used for the evaluation of the condition. To evaluate the condition we use a Twig macro where the actual script of the app is inserted:

```
{%% macro evaluate(%1$s) %%}
    %2$s
{%% endmacro %%}
```

We use a macro here because we want to allow the use of return statements. Even though return statements may be used outside of macros, Twig won't actually output the returned value. With the macro we can set the returned value to a variable and properly output the variable instead:

```
{%% set var = _self.evaluate(%1$s) %%}
{{ var }}
```

Making use of the macro we can avoid having to write a custom token parser to override return statements.

By calling `setConstraints` with the data stored in a json field of `app_script_condition`, the data will be transformed into actual constraints for the further validation of the condition.

Finally the `values` property contains an array of actual parameters, provided by the user when setting up the condition in the rule builder. Those parameters are passed as part of the context to Twig when rendering the script.

#### Complete draft for `ScriptRule`

```php
class ScriptRule extends Rule
{
    const CONSTRAINT_MAPPING = [
        'notBlank' => NotBlank::class,
        'arrayOfUuid' => ArrayOfUuid::class,
        'arrayOfType' => ArrayOfType::class,
        'choice' => Choice::class,
        'type' => Type::class,
    ];

    protected string $script = '';

    protected array $constraints = [];

    protected array $values = [];

    public function match(RuleScope $scope): bool
    {
        $context = array_merge(['scope' => $scope], $this->values);
        $script = new Script(
            $this->getName(),
            sprintf('
                {%% apply spaceless %%}
                    {%% macro evaluate(%1$s) %%}
                        %2$s
                    {%% endmacro %%}

                    {%% set var = _self.evaluate(%1$s) %%}
                    {{ var }}
                {%% endapply  %%}
            ', implode(', ', array_keys($context)), $this->script),
            $scope->getCurrentTime(),
            null
        );

        $twig = new TwigEnvironment(
            new ScriptTwigLoader($script),
            $script->getTwigOptions()
        );

        $twig->addExtension(new PhpSyntaxExtension());

        return filter_var(
            trim($twig->render($this->getName(), $context)),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function getConstraints(): array
    {
        return $this->constraints;
    }

    public function setConstraints(array $constraints): void
    {
        $this->constraints = [];
        foreach ($constraints as $name => $types) {
            $this->constraints[$name] = array_map(function ($type) {
                $arguments = $type['arguments'] ?? [];
                $class = self::CONSTRAINT_MAPPING[$type['name']];

                return new $class(...$arguments);
            }, $types);
        }
    }

    public function getName(): string
    {
        return 'scriptRule';
    }
}
```

### Changes for building rule payload with scripts

When an app script condition is used in a rule, the script from `app_script_condition` is assigned when building the payload. Also there should be an indexer for `app_script_condition`, that calls the `RulePayloadUpdater` for every `rule` the script is used in, to keep the payload up to date on changes made to the scripts, e.g. on lifecycle events of the corresponding app.

```diff
// src/Core/Content/Rule/DataAbstractionLayer/RulePayloadUpdater.php 

        $conditions = $this->connection->fetchAll(
-           'SELECT LOWER(HEX(rc.rule_id)) as array_key, rc.* FROM rule_condition rc  WHERE rc.rule_id IN (:ids) ORDER BY rc.rule_id',
+           'SELECT LOWER(HEX(rc.rule_id)) as array_key, rc.*, rs.script
+           FROM rule_condition rc
+           LEFT JOIN app_script_condition rs ON rc.script_id = rs.id AND rs.active = 1
+           WHERE rc.rule_id IN (:ids)
+           ORDER BY rc.rule_id',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        
        // ...
        
-           if ($rule['value'] !== null) {
+           if ($object instanceof ScriptRule) {
+               $object->assign([
+                   'script' => $rule['script'],
+                   'values' => $rule['value'] ? json_decode($rule['value'], true) : []
+               ]);
+           }
+           elseif ($rule['value'] !== null) {
                $object->assign(json_decode($rule['value'], true));
            }
```

### Defining a rule condition in the manifest

The following partial manifest defines a custom rule condition that requires a string value `operator` of either `=` or `!=` and an array `customerGroupIds` of id's for the entity `customer_group`.

The syntax for defining the parameters of a condition follows the same schema of defining config or custom fields.

```xml
<!-- ExampleApp/manifest.xml -->
<!-- ... -->
<rule-conditions>
    <rule-condition>
        <name>My custom rule condition</name>
        <group>customer</group>
        <script>customer-group-rule-script.twig</script>
        <constraints>
            <single-select name="operator">
                <label>Operator</label>
                <placeholder>Choose an operator...</placeholder>
                <options>
                    <option value="=">
                        <name>Is equal to</name>
                    </option>
                    <option value="!=">
                        <name>Is not equal to</name>
                    </option>
                </options>
                <required>true</required>
            </single-select>
            <multi-entity-select name="cusstomerGroupIds">
                <label>Customer groups</label>
                <placeholder>Choose customer groups...</placeholder>
                <entity>customer_group</entity>
                <required>true</required>
            </multi-entity-select>
        </constraints>
    </rule-condition>
</rule-conditions>
<!-- ... -->
```

The following rule script is logically identical to the hard-coded rule condition for matching that a customer is in a customer group.

```
{# ExampleApp/scripts/rule-conditions/customer-group-rule-script.twig #}

{% if scope.salesChannelContext.customer is not defined %}
    {% return false %}
{% endif %}

{% if operator == "=" %}
    {% return scope.salesChannelContext.customer.groupId in customerGroupIds %}
{% else %}
    {% return scope.salesChannelContext.customer.groupId not in customerGroupIds %}
{% endif %}
```

We also may offer Twig helper functions for evaluation of basic expressions for the more common use cases. So the above construct could be reduced to:

```
{% comparison.compare(operator, scope.salesChannelContext.customer.groupId, customerGroupIds) %}
```

### Implementation in administration

There will be only a single Vue component for all rule conditions based on scripts. The available fields and the type of each field will be dynamically built from the constraints of a rule script.

The following is a draft for a generic component that gets, sets and validates fields and their values as defined as constraints of an app's custom rule condition.

```javascript
Component.extend('sw-condition-script', 'sw-condition-base', {
    template,
    inheritAttrs: false,

    computed: {
        constraints() {
            return this.condition.script.constraints;
        },

        values() {
            const that = this;
            const values = {};

            Object.keys(this.constraints).forEach((key) => {
                Object.defineProperty(values, key, {
                    get: () => {
                        that.ensureValueExist();

                        return that.condition.value[key];
                    },
                    set: (value) => {
                        that.ensureValueExist();
                        that.condition.value = { ...that.condition.value, [key]: value };
                    },
                });
            });

            return values;
        },

        currentError() {
            let error = null;

            Object.keys(this.constraints).forEach((key) => {
                if (error) {
                    return;
                }

                const errorProperty = Shopware.State.getters['error/getApiError'](this.condition, `value.${key}`);

                if (errorProperty) {
                    error = errorProperty;
                }
            });

            return error;
        },
    },
    
    // ...
});
```

```html
<!-- /src/app/component/rule/condition-type/sw-condition-script/sw-condition-script.html.twig -->
{% block sw_condition_value_content %}
<div class="sw-condition-script sw-condition__condition-value">
    {% block sw_condition_script_fields %}
    <sw-arrow-field
        v-for="(constraint, index) in constraints"
        :disabled="disabled"
    >
        <!-- use the specific type of field as need for a constraint -->
        <!-- e.g. sw-entity-multi-select, sw-tagged-field, sw-number-field ... -->
    </sw-arrow-field>
    {% endblock %}
</div>
{% endblock %}
```

## Consequences

- Apps will be able to provide their own custom rule conditions, which will consequently be available in the administration's rule builder as any of the hard-coded rule conditions are.
- Rule scripting, first implemented for apps, eventually opens the door for scripting of rule conditions within the administration, e.g. by providing a code editor in the rule builder.
