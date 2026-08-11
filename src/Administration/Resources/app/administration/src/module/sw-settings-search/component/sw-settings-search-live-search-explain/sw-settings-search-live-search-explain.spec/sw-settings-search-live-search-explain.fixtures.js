/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

export async function createWrapper(props = {}) {
    return mount(
        await wrapTestComponent('sw-settings-search-live-search-explain', {
            sync: true,
        }),
        {
            props: {
                item: { extensions: { search: { _score: 0 } } },
                ...props,
            },
            global: {
                stubs: {
                    'sw-help-text': true,
                },
            },
        },
    );
}
