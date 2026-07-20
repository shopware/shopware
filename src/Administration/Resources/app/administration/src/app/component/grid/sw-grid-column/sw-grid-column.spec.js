/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

const defaultProps = {
    label: 'Test column',
};

async function createWrapper(props = defaultProps, swGridColumns = []) {
    return mount(await wrapTestComponent('sw-grid-column', { sync: true }), {
        props,
        global: {
            stubs: {},
            provide: {
                swGridColumns,
            },
        },
    });
}

describe('components/grid/sw-grid-column', () => {
    it.each([
        { name: 'to true if label is missing', label: null, expected: 1 },
        { name: 'to false if label is provided', label: 'Test column', expected: 1 },
    ])('should set spacer option $name', async ({ label, expected }) => {
        const swGridColumns = [];
        await createWrapper({ label }, swGridColumns);

        expect(swGridColumns).toHaveLength(expected);
    });
});
