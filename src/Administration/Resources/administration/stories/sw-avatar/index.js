import { storiesOf } from '@storybook/vue';
import vueComponents from '../helper/components.collector';
import SwagVueInfoAddon from '../addons/info-addon';

storiesOf('sw-avatar', module)
    .addDecorator(SwagVueInfoAddon)
    .add('Basic usage', () => ({
        components: {
            'sw-avatar': vueComponents.get('sw-avatar')
        },
        template: `
            <div>
                <sw-avatar image="https://d3iw72m71ie81c.cloudfront.net/female-14.jpeg" size="64px"></sw-avatar>
                <sw-avatar image="https://d3iw72m71ie81c.cloudfront.net/9476952d-55d4-48e1-8f6a-a4d226b6f3a5-zoro_HackerFund.jpg" size="64px"></sw-avatar>
            </div>
        `
    }));
