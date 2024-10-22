/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-cms-block-app-renderer', { sync: true }), {
        ...additionalOptions,
    });
}

describe('src/module/sw-cms/blocks/app/app-renderer/component/index.ts', () => {
    beforeEach(async () => {
        await flushPromises;
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should render all given block slots', async () => {
        const wrapper = await createWrapper({
            props: {
                block: {
                    slots: [
                        {
                            type: 'text',
                            slot: 'left',
                            config: {
                                content: 'Left',
                            },
                        },
                        {
                            type: 'text',
                            slot: 'right',
                            config: {
                                content: 'Right',
                            },
                        },
                    ],
                    customFields: {
                        slotLayout: {
                            grid: 'auto / auto auto',
                        },
                    },
                },
            },
            slots: {
                left: '<div class="left-slot"></div>',
                right: '<div class="right-slot"></div>',
            },
        });

        expect(wrapper.find('.left-slot').exists()).toBeTruthy();
        expect(wrapper.find('.right-slot').exists()).toBeTruthy();
    });

    it('should render the given slotLayout', async () => {
        const wrapper = await createWrapper({
            props: {
                block: {
                    slots: [
                        {
                            type: 'text',
                            slot: 'left',
                            config: {
                                content: 'Left',
                            },
                        },
                        {
                            type: 'text',
                            slot: 'right',
                            config: {
                                content: 'Right',
                            },
                        },
                    ],
                    customFields: {
                        slotLayout: {
                            grid: 'auto / auto auto',
                        },
                    },
                },
            },
            slots: {
                left: '<div class="left-slot"></div>',
                right: '<div class="right-slot"></div>',
            },
        });

        expect(wrapper.find('.sw-cms-block-app-renderer').attributes('style')).toBe(
            'display: grid; grid: auto / auto auto;',
        );
    });

    it('should render the fallback slotLayout', async () => {
        const wrapper = await createWrapper({
            props: {
                block: {
                    slots: [
                        {
                            type: 'text',
                            slot: 'left',
                            config: {
                                content: 'Left',
                            },
                        },
                        {
                            type: 'text',
                            slot: 'right',
                            config: {
                                content: 'Right',
                            },
                        },
                    ],
                },
            },
            slots: {
                left: '<div class="left-slot"></div>',
                right: '<div class="right-slot"></div>',
            },
        });

        expect(wrapper.find('.sw-cms-block-app-renderer').attributes('style')).toBe('display: grid; grid: auto / auto;');
    });
});
