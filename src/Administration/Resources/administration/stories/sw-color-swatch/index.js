import { storiesOf } from '@storybook/vue';
import vueComponents from '../helper/components.collector';
import SwagVueInfoAddon from '../addons/info-addon';

storiesOf('sw-color-swatch', module)
    .addDecorator(SwagVueInfoAddon)
    .add('Basic usage', () => ({
        components: {
            'sw-color-swatch': vueComponents.get('sw-color-swatch')
        },
        template: `
            <div>
                <sw-color-swatch variant="success"></sw-color-swatch>
                <sw-color-swatch variant="warning"></sw-color-swatch>
                <sw-color-swatch variant="error"></sw-color-swatch>
                <sw-color-swatch variant="info"></sw-color-swatch>
                <sw-color-swatch></sw-color-swatch>
                <sw-color-swatch color="#81ecec"></sw-color-swatch>
                
                <sw-color-swatch variant="success" :rounded="true"></sw-color-swatch>
                <sw-color-swatch variant="warning" :rounded="true"></sw-color-swatch>
                <sw-color-swatch variant="error" :rounded="true"></sw-color-swatch>
                <sw-color-swatch variant="info" :rounded="true"></sw-color-swatch>
                <sw-color-swatch :rounded="true"></sw-color-swatch>
                <sw-color-swatch color="#81ecec" :rounded="true"></sw-color-swatch>
            </div>
        `
    }));
