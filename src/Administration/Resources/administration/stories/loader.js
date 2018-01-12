import { storiesOf } from '@storybook/vue';
import centered from '@storybook/addon-centered';
import { withKnobs, boolean } from '@storybook/addon-knobs/vue';
import vueComponents from './helper/components.collector';

storiesOf('Loading Indicator', module)
    .addDecorator(centered)
    .addDecorator(withKnobs)
    .add('Loader usage', () => ({
        components: {
            'sw-loader': vueComponents.get('sw-loader'),
            'sw-card': vueComponents.get('sw-card')
        },
        data() {
            return {
                isLoaderWorking: boolean('Is loading?', true)
            };
        },
        template: `
            <sw-card title="Card with loader" style="width: 400px; position: relative">
                <p>
                Lorem ipsum dolor sit amet, consectetur adipisicing elit. Consequatur dicta eveniet neque nulla odit
                possimus quia, repellat sint. Accusantium adipisci cumque dicta eius necessitatibus, nemo quis quo 
                ratione sequi tenetur.
                </p>
                <sw-loader v-if="isLoaderWorking"></sw-loader>
            </sw-card>
        `
    }));
