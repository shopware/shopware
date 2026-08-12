/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import swMediaFolderThumbnail from './index';

async function createWrapper(props = {}) {
    return mount(await wrapTestComponent('sw-media-folder-thumbnail', { sync: true }), {
        props,
    });
}

describe('components/media/sw-media-folder-thumbnail', () => {
    it('should render the folder artwork inline as svg', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('svg.sw-media-folder-thumbnail').exists()).toBe(true);
    });

    it('should color the artwork with theme-aware meteor tokens', async () => {
        const wrapper = await createWrapper();
        const path = wrapper.find('path');

        expect(path.attributes('fill')).toBe('var(--color-background-secondary-default)');
        expect(path.attributes('stroke')).toBe('var(--color-border-primary-default)');
        expect(path.attributes('vector-effect')).toBe('non-scaling-stroke');
    });

    it.each([
        [
            'back',
            'is--back',
        ],
        [
            'back-breadcrumb',
            'is--back-breadcrumb',
        ],
    ])('should render the %s variant with a back chevron', async (variant, variantClass) => {
        const wrapper = await createWrapper({ variant });
        const svg = wrapper.find(`svg.${variantClass}`);

        expect(svg.exists()).toBe(true);
        expect(svg.findAll('path')).toHaveLength(2);
        expect(svg.findAll('path')[1].attributes('fill')).toBe('var(--color-icon-brand-default)');
    });

    it('should reject unknown variants', () => {
        const variantProp = swMediaFolderThumbnail.props.variant;

        expect(variantProp.validator('default')).toBe(true);
        expect(variantProp.validator('back')).toBe(true);
        expect(variantProp.validator('back-breadcrumb')).toBe(true);
        expect(variantProp.validator('unknown')).toBe(false);
    });
});
