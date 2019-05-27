[titleEn]: <>(Changes in sw-field component)

The `sw-field` component got a complete overhaul in order to remove unused properties, doubled configuration
and a lot of unnecessary template and logic inheritance.

### New structure for fields

The `sw-field` component now uses slot mechanics and property consumption instead of component inheritance.

If you inspect an `sw-field` you may notice that it has several child components depending on your fields type:
```XML
<sw-field>
    <sw-contextual-field>
        <sw-block-field>
            <sw-base-field>
```

This child components consume basic information for example `label` and `size` properties, apply them to their own 
elements and expose them back to you via slots.

### Remove properties, types and components

* The `sw-number-field` now changes its model on 'change' instead of input. This makes it easier to validate numbers
* `sw-fiel-addition`: Was removed. Its purpose was to style the prefix and suffix sections of `sw-field`
* `sw-field-label`: Was removed. 
* `sw-field-help-text`: We removed the `sw-field-help-text` because it wasn't really used in the project.
Also this old grey description text did not comply with our design system any more.
* `tooltipText`: Having two properties for `helpText` and `tooltipText` could be confusing
so we removed the  property in favor of `helpText`. The `helpText` property is now rendered in an `sw-help-text` bubble.
* `type="bool"`: It was very confusing to have to two switch typed variants of `sw-field` (`type="switch"` and `type="bool"`)
which' only difference was a border. We removed `<sw-field type="bool">` an replace it with an `bordered` attribute.
```HTMl
<sw-field type="switch" bordered [...] ></sw-field>

```     
* `prefix` and `suffix`: We removed the `prefix` and `suffix` properties in all fields in favour of using slots instead.
This change removes the possibility to define two different values. With Vue.js' new slot syntax defining prefixes and suffixes is easy:
```HTML
<sw-fiel type="text" [...]>
    <template #prefix>
      {{ dynamicPrefix }}
    </template>

    <template #suffix>
        constant
    </template>
</sw-fiel>


``` 

### Added properties

* size (String): Got new values `'medium'` and `small`. Also the the sizes of the input fields changed to `small` = 32px
`medium` = 40px and `default` = 48px height.
* type="switch" got a new prop `bordered` (Boolean) that made `type="boolean"` obsolete.
* type="select" got a new prop `aside` (Boolean) to set the label left to the select box.

### "Strict types" in properties

We removed the `sw-inline-snippet` mixin from all `sw-fields` and set the translated properties (e.g. `label`) to type string.
That means you can't pass objects with translations anymore. 

### sw-base-field

The `sw-base-field` component consumes basic information about your form field and is intended to display a basic header and error information. 

#### Props
* name (String): Use this to override the exposed identifier  
* label (String): The label of the component 
* helpText (String): The help text is displayed as an `sw-help-text` component on the top right corner of the field 
* error/errorMessage (String, Object): Don't mind them now as they will be refactored soon
* disabled (Boolean): Sets the disabled State
* required (Boolean): (not fully implemented yet)
* inherited (Boolean): (not fully implemented yet)

#### Slots

* `sw-field-input` scope: { identification, disabled }
    * identification (String): If name property is set this will be just the given name. If not this the value is `sw-field--${Uuid}`.
which can be used to link elements that have an `for` attribute to your inputs.
    * disabled (Boolean): You may need the information in your slot since you actually do not want to have an own disabled prop.

### sw-block-field

The main purpose of the `sw-block-field` component is intended to take care of sizes borders and border colors

#### Props
* size (String): Defines the can be either 'small', 'medium', or 'default' 

#### Slots

* `sw-field-input` scope: { identification, disabled, swBlockSize, setFocusClass, removeFocusClass }
    * identification, disabled are just exposed from `sw-base-field`
    * swBlockSize (String): A CSS selector that can be used by your component to react to different sizes
    * setFocusClass, removeFocusClass (function): You can use this in your component to set or remove the focuses state of an `sw-block-field`

(e.g. bind it to the click of a button)

### sw-contextual-field

The `sw-contextual-field` is intended to render a "context" to the field. This context is displayed as pre and suffix.

#### Props

* None

#### Slots

* `sw-contextual-field-prefix` scope: { disabled, identification }
* `sw-contextual-field-suffix` scope: { disabled, identification }
* `sw-field-input` scope: { identification, error, disabled, swBlockSize, setFocusClass, removeFocusClass, hasSuffix, hasPrefix }
   * identification, error, disabled, swBlockSize, setFocusClass, removeFocusClass - as described above
   * hasSuffix, hasPrefix: selfexplaining

### Follow ups

* In the next few days we will apply the `sw-field` structure to `sw-media-field`
and `sw-select`.
* Errorhandling and fieldvalidation are in progress
* integrate inherited values

