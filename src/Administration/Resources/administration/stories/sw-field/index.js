import { storiesOf } from '@storybook/vue';
import { withKnobs } from '@storybook/addon-knobs/vue';

import SwagVueInfoPanel from '../addons/info-addon';
import vueComponents from '../helper/components.collector';

import description from './description.md';

storiesOf('sw-field', module)
    .addDecorator(SwagVueInfoPanel)
    .addDecorator(withKnobs)
    .add('Basic usage', () => ({
        description,
        components: {
            'sw-field': vueComponents.get('sw-field')
        },
        data() {
            return {
                product: {
                    title: 'Lorem ipsum set dolor',
                    price: {
                        gross: 40.95
                    },
                    description: 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
                    manufacturerId: 4,
                    active: true,
                    released: false,
                    option: 1
                },
                user: {
                    email: 'lorem@ipsum.dolor'
                },
                entries: [
                    { value: 1, label: 'Lorem' },
                    { value: 2, label: 'Ipsum' },
                    { value: 3, label: 'Consetetur' },
                    { value: 4, label: 'Sadipscing' },
                    { value: 5, label: 'Nonumy' },
                    { value: 6, label: 'Invidunt' }
                ]
            };
        },
        template: `
<div>
    <!-- Text field -->
    <sw-field type="text"
              label="Title" 
              name="title" 
              placeholder="Your product title..."
              v-model="product.title">
    </sw-field>

    <!-- Number field with suffix -->
    <sw-field type="number"
              label="Price"
              name="price"
              suffix="€"
              step="0.01"
              placeholder="Product price..."
              v-model="product.price.gross">
    </sw-field>

    <!-- Text area -->
    <sw-field type="textarea"
              label="Description" 
              name="descriptionLong" 
              placeholder="Your product description..."
              v-model="product.description">
    </sw-field>

    <!-- Basic select field -->
    <sw-field type="select"
              label="Manufacturer" 
              name="manufacturer" 
              placeholder="Select entry"
              v-model="product.manufacturerId">
        <option slot="options" v-for="entry in entries" :value="entry.value">{{ entry.label }}</option>
    </sw-field>

    <!-- Email field with validation -->
    <sw-field type="email"
              label="Email" 
              name="email" 
              placeholder="Your email address..."
              helpText="Type in a valid email address. (required)"
              validation="required, email"
              v-model="user.email">
    </sw-field>

    <!-- Password field -->
    <sw-field type="password"
              label="Password" 
              name="password" 
              placeholder="Your password...">
    </sw-field>
    
    <!-- Password field with visibility toggle -->
    <sw-field type="password"
              label="Password with visibility toggle" 
              name="password_visibility"
              value="password" 
              :togglePasswordVisibility="true">
    </sw-field>

    <!-- Checkbox field -->
    <sw-field type="checkbox" 
              label="Active status"
              name="active"
              helpText="Set the active state."
              v-model="product.active">
    </sw-field>

    <!-- Switch field -->
    <sw-field type="switch" 
              label="Released status"
              name="released"
              helpText="Set the released state. (required)"
              validation="required"
              v-model="product.released">
    </sw-field>

    <!-- Switch field -->
    <sw-field type="radio" 
              label="Additional options"
              name="options"
              :options="entries"
              v-model="product.option">
    </sw-field>

</div>`
    }));
