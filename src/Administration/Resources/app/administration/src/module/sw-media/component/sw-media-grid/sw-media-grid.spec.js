/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

function createClickEventWithPath(classes = []) {
    return {
        target: document.createElement('div'),
        composedPath: () => {
            return classes.map((cssClass) => {
                return { classList: [cssClass] };
            });
        },
    };
}

describe('src/module/sw-media/component/sw-media-grid', () => {
    let wrapper;

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
    });

    it('emits selection clear on outside click', async () => {
        wrapper = mount(await wrapTestComponent('sw-media-grid', { sync: true }));

        wrapper.vm.clearSelectionOnClickOutside(createClickEventWithPath());

        expect(wrapper.emitted('media-grid-selection-clear')).toHaveLength(1);
    });

    it('does not emit selection clear for clicks inside mt-modal', async () => {
        wrapper = mount(await wrapTestComponent('sw-media-grid', { sync: true }));

        wrapper.vm.clearSelectionOnClickOutside(createClickEventWithPath(['mt-modal']));

        expect(wrapper.emitted('media-grid-selection-clear')).toBeUndefined();
    });

    it('does not emit selection clear for clicks on mt-modal backdrop', async () => {
        wrapper = mount(await wrapTestComponent('sw-media-grid', { sync: true }));

        wrapper.vm.clearSelectionOnClickOutside(createClickEventWithPath(['mt-modal-root__backdrop']));

        expect(wrapper.emitted('media-grid-selection-clear')).toBeUndefined();
    });
});
