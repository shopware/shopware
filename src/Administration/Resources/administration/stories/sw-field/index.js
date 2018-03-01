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
                entries: [
                    { id: 1, name: 'Homer Simpson' },
                    { id: 2, name: 'Bart Simpson' },
                    { id: 3, name: 'Marge Simpson' },
                    { id: 4, name: 'Lisa Simpson' },
                    { id: 4, name: 'Mr. Burns' },
                    { id: 5, name: 'Ned Flanders' }
                ]
            };
        },
        template: `
            <div>
                <!-- Text field -->
                <sw-field label="Title" id="title" type="text" name="name" placeholder="Your product title..."></sw-field>
                
                <!-- Password field -->
                <sw-field label="Password" id="password" type="password" name="name" placeholder="Your password..."></sw-field>
                
                <!-- Number field with suffix -->
                <sw-field label="Price" id="price" type="number" name="price" suffix="€" placeholder="Product price..."></sw-field>
                
                <!-- Text area -->
                <sw-field label="Description" id="descriptionLong" type="textarea" name="descriptionLong" placeholder="Your product description..."></sw-field>
                
                <!-- Basic select field -->
                <sw-field label="Select box" id="manufacturer" type="select" name="manufacturer" placeholder="Select entry">
                    <option v-for="entry in entries" :value="entry.id">{{ entry.name }}</option>
                </sw-field>
            </div>`
    }));
