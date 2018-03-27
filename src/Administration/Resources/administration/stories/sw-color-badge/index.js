import { storiesOf } from '@storybook/vue';
import vueComponents from '../helper/components.collector';
import SwagVueInfoAddon from '../addons/info-addon';

storiesOf('sw-color-badge', module)
    .addDecorator(SwagVueInfoAddon)
    .add('Basic usage', () => ({
        components: {
            'sw-color-badge': vueComponents.get('sw-color-badge')
        },
        template: `
            <div>
                <sw-color-badge variant="success"></sw-color-badge>
                <sw-color-badge variant="warning"></sw-color-badge>
                <sw-color-badge variant="error"></sw-color-badge>
                <sw-color-badge variant="info"></sw-color-badge>
                <sw-color-badge></sw-color-badge>
                <sw-color-badge color="#81ecec"></sw-color-badge>
                
                <sw-color-badge variant="success" :rounded="true"></sw-color-badge>
                <sw-color-badge variant="warning" :rounded="true"></sw-color-badge>
                <sw-color-badge variant="error" :rounded="true"></sw-color-badge>
                <sw-color-badge variant="info" :rounded="true"></sw-color-badge>
                <sw-color-badge :rounded="true"></sw-color-badge>
                <sw-color-badge color="#81ecec" :rounded="true"></sw-color-badge>
            </div>
        `
    }));
