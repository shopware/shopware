import { storiesOf } from '@storybook/vue';

import SwagVueInfoPanel from '../addons/info-addon';
import vueComponents from '../helper/components.collector';

import description from './description.md';

storiesOf('sw-address', module)
    .addDecorator(SwagVueInfoPanel)
    .add('Basic usage', () => ({
        description,
        components: {
            'sw-address': vueComponents.get('sw-address'),
        },
        props: {
            exampleAddress: {
                type: Object,
                default() {
                    return {
                        company: 'Shopware AG',
                        salutation: 'Herr',
                        firstName: 'Max',
                        lastName: 'Mustermann',
                        street: 'Ebbinghof 10',
                        zipcode: '48624',
                        city: 'Shöppingen',
                        country: {
                            name: 'Germany'
                        }
                    }
                }
            }
        },
        template: `<sw-address :address="exampleAddress"></sw-address>`
    }));
