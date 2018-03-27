import { storiesOf } from '@storybook/vue';
import vueComponents from '../helper/components.collector';
import SwagVueInfoAddon from '../addons/info-addon';

storiesOf('sw-context-button', module)
    .addDecorator(SwagVueInfoAddon)
    .add('Basic usage', () => ({
        components: {
            'sw-context-button': vueComponents.get('sw-context-button'),
            'sw-context-menu-item': vueComponents.get('sw-context-menu-item')
        },
        template: `
            <sw-context-button style="margin-left: 300px;">
                <sw-context-menu-item icon="small-pencil-paper">
                    Artikel editieren
                </sw-context-menu-item>
                <sw-context-menu-item icon="small-default-x-line-medium" :disabled="true">
                    Artikel löschen
                </sw-context-menu-item>
                <sw-context-menu-item icon="small-copy" :disabled="true">
                    Artikel duplizieren
                </sw-context-menu-item>
            </sw-context-button>
        `
    }));
