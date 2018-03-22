import { storiesOf } from '@storybook/vue';
import vueComponents from '../helper/components.collector';
import SwagVueInfoAddon from '../addons/info-addon';

storiesOf('sw-pagination', module)
    .addDecorator(SwagVueInfoAddon)
    .add('Basic usage', () => ({
        components: {
            'sw-pagination': vueComponents.get('sw-pagination')
        },
        template: `
            <div>
                <sw-pagination :total="1500" :offset="125" :limit="25"></sw-pagination>
            </div>
        `
    }));
