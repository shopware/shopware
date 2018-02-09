# CSS naming conventions

Because the shopware administration is a component based application with reusable elements, the CSS structure is also component-driven. The Markup and CSS of the administration is using BEM as a naming convention. 

## BEM Introduction

* BEM stands for "Block Element Modifier".
* In our case "Block" would be equal to the root element of a Vue component.
* "Element" describes the elements which are **inside** the component.
* "Modifier" is an additional class which can adjust the styling.
* Further reading: <a href="http://getbem.com/">getbem.com</a>

### CSS Example:
```
/* Block component */
.sw-card {}

/* Element that depends upon the block */
.sw-card__body {}

/* Modifier that changes the style of the block (permanently, static) */
.sw-card--danger {}
.sw-card--large {}

/* State (temporarily, dynamic)  */
.is--selected {}
.is--active {}
```

### Markup Example for a component:
```
<div class="sw-card sw-card--large">
  <div class="sw-card__header">
    <h4 class="sw-card__title">
      Card Title
    </h4>
  </div>
  <div class="sw-card__body">
  	Lorem ipsum dolor sit amet
  	<div class="sw-card__divider sw-card__divider--primary"></div>
  	Lorem ipsum dolor sit amet
  </div>
  <div class="sw-card__footer">
    Card Footer
  </div>
</div>
```
All CSS sub-classes rely on the root element of the component &ndash; even when they are nested further inside the markup. In the above example the root element is `sw-card`. The nested `<h4>` element `sw-card__title` relies on `sw-card` and not on `header`. This approach is recommended in the <a href="http://getbem.com/faq/#css-nested-elements">BEM documentation</a>.

## LESS variable naming and structure

### General variable naming convention

- Each variable should be prefixed with its purpose in a meaningful way.<br>
  Example: Variables which contain HEX or rgba values should begin with `@color- ...`.
- All variables should be kebab-case. Please avoid using camelCase or snake_case when possible.

```
@color-primary:            #f00;
@z-index-dialog:           9000;
@border-radius-default:    6px;
@color-box-shadow-default: rgba(0, 0, 0, 0.2);
@width-content:            1200px;
@size-avatar-default:      50px;
@font-family-default:      'Source Sans Pro', Arial, sans-serif;

```

### Global variables

- In addition to the components own LESS files, the administration is also offering global variables to provide an easy way to develop styling.
- The global color variables match the color names in our design guidelines.

##### Global variables.less example:

```
// Primary
@color-shopware-blue:     #189EFF;
@color-biscay:            #16325C;
@color-deep-cove:		  #303A4F;
@color-crimson:           #DE294C;
@color-pumpkin-spice:     #FFB75D;

// Neutrals
@color-kashmir:           #54698D;
@color-iron:              #FAFBFC;
@color-cadet-blue:        #E8F6FF;

...
```

- The global variables are used inside the components but will be assigned/re-mapped to component specific variable names. For example, the variable `@color-shopware-blue` could be used for a border color or a background color inside a component.
- As a result, the colors and other styling can be adjusted for each component individually.
- The component specitif variables are declared at the top of the components LESS file.
- They should begin with the component name like `@sw-button-color-background`.

##### Button component example:

```
@sw-button-primary-color-background: @color-shopware-blue;
@sw-button-primary-color-text:       @color-iron;
@sw-button-border-radius:            @border-radius-default;

.sw-button {
  border-radius: @sw-button-border-radius;
  
  &.sw-button--primary {
    color: @sw-button-primary-color-text;
    background-color: @sw-button-primary-color-background;
  }  
}
```