import { storiesOf } from '@storybook/vue';
import { action } from '@storybook/addon-actions';
import centered from '@storybook/addon-centered';
import { withKnobs, text, boolean } from '@storybook/addon-knobs/vue';
import vueComponents from './helper/components.collector';

storiesOf('Buttons', module)
    .addDecorator(centered)
    .addDecorator(withKnobs)
    .add('Basic usage', () => ({
        components: {
            'sw-button': vueComponents.get('sw-button')
        },
        methods: {
            onClick(componentName, event) {
                action('button-clicked')(event, this.$refs[componentName]);
            }
        },
        template: `
            <div>
                <sw-button :isPrimary="true" @click="onClick('primary', $event)" ref="primary">Primary button</sw-button>
                <sw-button ref="secondary" @click="onClick('secondary', $event)">Secondary button</sw-button>
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
            <div>
                <sw-button 
                    :isPrimary="true" 
                    :isDisabled="true" 
                    ref="primary" 
                    @click="onClick('primary', $event)">
                    Disabled primary button
                </sw-button>
                <sw-button 
                    :isDisabled="true" 
                    ref="secondary" 
                    @click="onClick('primary', $event)">
                    Disabled secondary button
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
                isPrimaryButton: boolean('Primary', true),
                isDisabledButton: boolean('Disabled', false)
            };
        },
        template: `
            <div>
                <sw-button 
                    :isPrimary="isPrimaryButton" 
                    :isDisabled="isDisabledButton" 
                    ref="button" 
                    @click="onClick($event)">
                    {{ text }}
                </sw-button>
            </div>`
    }));
