# Form Handling
In a classic website you use forms for editing data and sending it to the server. In the new Shopware administration you have to think a little bit different. The complete view of the administration is rendered by a stateful rendering framework. Therefore the view is based on data and not the other way round. So to edit the data we have to bind it to the view for example to a form input element. This makes it possible to edit the data and change its state via the form input. But there will be no actual submitting of a form to complete the process. Rather there will be an api service which sends the current state of the data to the server.

## Input Bindings
To bind data to a HTML form element the VueJS framework has a special directive property called `v-model`. You can read more about its functionality in the [official VueJS documentation](https://vuejs.org/v2/guide/forms.html).

In Shopware we created special form components for the most common use cases. So you already have a set of nice form elements in the style of the Shopware administration framework which provide a centralized way of editing your data. The best thing about them is, that they also work with the standard `v-model` directive from VueJS. So no special cases needed, just your basic VueJS knowledge. But to make our lives easier we added some additional features build on top oft it, like error handling and validation.

To create a new field you can use the `<sw-field>` component. This component provides several types of common from inputs.

**Example:**
```html
<sw-field type="number"
          name="price"
          label="Product price"
          placeholder="Enter the gross price"
          suffix="€"
          step="0.01"
          v-model="product.price.gross">
</sw-field>
```

This HTML code will render a number input for example for editing the price of a product. It comes with some specific properties provided by the component it self like `label` and `suffix`. But you can also pass classic attributes which are accepted by the type of input like the `step=""` attribute of the number input element. For a complete overview of the possible properties and input types you can take a look in our component library documentation.

To bind the data of the product gross price to the field we use the standard `v-model` directive and pass the data object we want to bind. The `v-model` will pass the data in a value property to the component which will be handled internally. The field component will update the data corresponding to the changes made via the input. So the data will always have the same state as the value of the field.

## Field Validation
The api of course will validate all data you will send to it, but for usability aspects it would be nice to also offer some local validation. This helps the user while editing the data and gives direct feedback. For this reason we created the possibility to define different validation rules directly at the field component. There are several validation rules you can pass to a `validation` property to the `<sw-field>` component.

**Example:**
```html
<sw-field type="email"
          name="customerEmail"
          label="The customer email address"
          validation="required, email"
          v-model="customer.email"
</sw-field>
```

In this case the data bound to the field should not be empty because of the *"required"* validation and has also to be a valid email address because of the *"email"* validation. If the validation of the data fails the field gets a specific styling which will visualise the invalid state to the user.

This functionality can also be used in other custom components. The validation is accessible as a global mixin which you can use in a component by adding `Shopware.Mixin.getByName('validation')` to your mixin list. The mixin will validate a `value` property of your component, so this works also perfectly together with `v-model`.

## Error Handling
When the data is finally send to the api it will be processed by a server side validation. If there are any validation errors the api will send a response containing detailed information for all errors. The nice thing about the generated errors is, that they contain a pointer path to the corresponding data which caused the error. With the `<sw-field>` component we implemented the functionality to handle these errors automatically based on the bound value. This has the requirement that the object path of the data binding matches the *module* under which the error is registered in the global error state. For example all main entity state modules will register corresponding errors under the same name. The *product* state module for example will register all api errors under the key ```product```. So to automatically match the input binding with the corresponding errors you have to pass the data under the *product* key to your template. When working with existing entities of Shopware you can simply use one of our helper mixins which provide all the business logic for the corresponding entity including data bindings.  
