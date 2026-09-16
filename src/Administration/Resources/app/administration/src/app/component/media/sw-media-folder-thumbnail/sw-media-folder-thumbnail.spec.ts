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

    it('should paint the default folder in the given color', async () => {
        const wrapper = await createWrapper({ color: '#57D9A3' });
        const svg = wrapper.find('svg.sw-media-folder-thumbnail');

        expect(svg.classes()).toContain('is--colored');
        expect(svg.attributes('style')).toContain('--sw-media-folder-thumbnail-color: #57D9A3');
    });

    it('should stay neutral without a color', async () => {
        const wrapper = await createWrapper();
        const svg = wrapper.find('svg.sw-media-folder-thumbnail');

        expect(svg.classes()).not.toContain('is--colored');
        expect(svg.attributes('style')).toBeUndefined();
    });

    it.each([
        'back',
        'back-breadcrumb',
    ])('should ignore the color on the %s variant', async (variant) => {
        const wrapper = await createWrapper({ variant, color: '#57D9A3' });
        const svg = wrapper.find('svg.sw-media-folder-thumbnail');

        expect(svg.classes()).not.toContain('is--colored');
        expect(svg.findAll('path')[0].attributes('fill')).toBe('var(--color-background-brand-default)');
    });

    it('should reject unknown variants', () => {
        const { variant: variantProp } = (
            swMediaFolderThumbnail as unknown as {
                props: { variant: { validator: (value: string) => boolean } };
            }
        ).props;

        expect(variantProp.validator('default')).toBe(true);
        expect(variantProp.validator('back')).toBe(true);
        expect(variantProp.validator('back-breadcrumb')).toBe(true);
        expect(variantProp.validator('unknown')).toBe(false);
    });
});
