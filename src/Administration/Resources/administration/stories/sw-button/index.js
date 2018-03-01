import { storiesOf } from '@storybook/vue';
import { action } from '@storybook/addon-actions';
import { withKnobs, text, boolean } from '@storybook/addon-knobs/vue';

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
        template: `
            <div class="is-centered">
                <div class="primary-buttons">
                    <sw-button :isPrimary="true" @click="onClick($event)">Default Primary button</sw-button>
                    <sw-button :isPrimary="true" :isGhost="true" @click="onClick($event)">Ghost Primary button</sw-button>
                    <sw-button :isPrimary="true" :isLarge="true" @click="onClick($event)">Large Primary button</sw-button>
                    <sw-button :isPrimary="true" :isSmall="true" @click="onClick($event)">Small Primary button</sw-button>
                </div>
                
                <div class="default-buttons">
                    <sw-button @click="onClick($event)">Default button</sw-button>
                    <sw-button :isGhost="true" @click="onClick($event)">Ghost button</sw-button>
                    <sw-button :isLarge="true" @click="onClick($event)">Large button</sw-button>
                    <sw-button :isSmall="true" @click="onClick($event)">Small button</sw-button>
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
        template: `
            <sw-button 
                :isPrimary="true" 
                :isDisabled="true" 
                ref="primary" 
                @click="onClick('primary', $event)">
                Disabled primary button
            </sw-button>
        `
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
                isPrimaryButton: boolean('Primary', true),
                isDisabledButton: boolean('Disabled', false)
            };
        },
        template: `
            <sw-button 
                :isPrimary="isPrimaryButton" 
                :isDisabled="isDisabledButton" 
                ref="button" 
                @click="onClick($event)">
                {{ text }}
            </sw-button>`
    }));
