import { storiesOf } from '@storybook/vue';
import { action } from '@storybook/addon-actions';
import { withKnobs, text, boolean, select } from '@storybook/addon-knobs/vue';

import SwagVueInfoPanel from '../addons/info-addon';
import vueComponents from '../helper/components.collector';

import description from './description.md';

storiesOf('sw-button', module)
    .addDecorator(SwagVueInfoPanel)
    .addDecorator(withKnobs)
    .add('Basic usage', () => ({
        description,
        components: {
            'sw-button': vueComponents.get('sw-button')
        },
        methods: {
            onClick(event) {
                action('button-clicked')(event);
            }
        },
        template:
            `<div>
                <div class="button-variants" style="margin-bottom: 20px;">
                    <sw-button @click="onClick($event)">Default button</sw-button>
                    <sw-button variant="primary" @click="onClick($event)">Primary button</sw-button>
                    <sw-button variant="ghost" @click="onClick($event)">Ghost button</sw-button>
                </div>

                <div class="button-sizes">
                    <sw-button size="small" @click="onClick($event)">Small button</sw-button>
                    <sw-button @click="onClick($event)">Medium button</sw-button>
                    <sw-button size="large" @click="onClick($event)">Large button</sw-button>
                </div>
            </div>`
    }))
    .add('Disabled buttons', () => ({
        components: {
            'sw-button': vueComponents.get('sw-button')
        },
        methods: {
            onClick(componentName, event) {
                action('button-clicked')(event, this.$refs[componentName]);
            }
        },
        template:
            `<div>
                <sw-button disabled 
                           ref="primary" 
                           @click="onClick('primary', $event)">
                           Disabled button
                </sw-button>
                <sw-button variant="primary" 
                           disabled 
                           ref="primary" 
                           @click="onClick('primary', $event)">
                           Disabled primary button
                </sw-button>
                <sw-button variant="ghost" 
                           disabled 
                           ref="primary" 
                           @click="onClick('primary', $event)">
                           Disabled ghost button
                </sw-button>
            </div>`
    }))
    .add('Interactive button', () => ({
        components: {
            'sw-button': vueComponents.get('sw-button')
        },
        methods: {
            onClick(event) {
                action('button-clicked')(event, this.$refs.button);
            }
        },
        data() {
            return {
                text: text('Button text', "I'm a button"),
                isDisabledButton: boolean('Disabled', false),
                isBlockButton: boolean('Block', false),
                variant: select('Variant', { primary: 'Primary', ghost: 'Ghost' }, ''),
                size: select('Size', { small: 'Small', large: 'Large' }, '')
            };
        },
        template: `
            <sw-button 
                :variant="variant"
                :size="size"
                :block="isBlockButton"
                :disabled="isDisabledButton"
                ref="button"
                @click="onClick($event)">
                {{ text }}
            </sw-button>`
    }));
