/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

describe('components/sw-select-result-list', () => {
    it('scrolls the active item of its own list into view when navigating with the keyboard', async () => {
        document.body.insertAdjacentHTML(
            'beforeend',
            '<div class="sw-select-result-list__content"><li class="is--active"></li></div>',
        );
        const otherListItem = document.body.lastElementChild.firstElementChild;
        otherListItem.scrollIntoView = jest.fn();

        const wrapper = mount(await wrapTestComponent('sw-select-result-list', { sync: true }), {
            attachTo: document.body,
            props: {
                options: [
                    { id: 'first' },
                    { id: 'second' },
                ],
            },
            slots: {
                'result-item': `<template #result-item="{ index }">
                    <li :class="['sw-select-result', { 'is--active': index === 1 }]"></li>
                </template>`,
            },
            global: {
                stubs: {
                    'sw-popover': await wrapTestComponent('sw-popover', { sync: true }),
                    'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                    'sw-icon': true,
                },
            },
        });
        await flushPromises();

        const activeItem = wrapper.vm.$refs.popoverContent.querySelector('.is--active');
        activeItem.scrollIntoView = jest.fn();

        wrapper.vm.navigate({ key: 'ArrowDown' });
        await flushPromises();

        expect(activeItem.scrollIntoView).toHaveBeenCalledWith({ block: 'nearest' });
        expect(otherListItem.scrollIntoView).not.toHaveBeenCalled();

        wrapper.unmount();
        document.body.lastElementChild.remove();
    });
});
