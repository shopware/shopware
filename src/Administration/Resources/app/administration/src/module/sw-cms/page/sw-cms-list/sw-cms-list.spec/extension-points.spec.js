/**
 * @sw-package discovery
 */
import template from 'src/module/sw-cms/page/sw-cms-list/sw-cms-list.html.twig';

const TemplateFactory = Shopware.Template;

async function getMethods() {
    const component = await wrapTestComponent('sw-cms-list', {
        sync: true,
    });

    return component.methods;
}

/**
 * Renders the real `sw-cms-list` template together with the given overrides, the same way the
 * template factory merges plugin overrides at runtime.
 */
function renderWithOverrides(overrides) {
    const name = `sw-cms-list-extension-points-${overrides.map((override) => override.name).join('-')}`;

    TemplateFactory.register(name, template);
    overrides.forEach((override, index) => {
        TemplateFactory.override(name, override.raw, index);
    });

    return TemplateFactory.getRenderedTemplate(name);
}

function overrideBlock(name, blockName, markup) {
    return {
        name,
        raw: `{% block ${blockName} %}{% parent %}${markup}{% endblock %}`,
    };
}

describe('module/sw-cms/page/sw-cms-list/extension-points', () => {
    it('marks the configured product and category layouts as default', async () => {
        const methods = await getMethods();
        const context = {
            defaultProductId: 'product-layout-id',
            defaultCategoryId: 'category-layout-id',
        };

        expect(methods.isDefaultLayout.call(context, { id: 'product-layout-id' })).toBe(true);
        expect(methods.isDefaultLayout.call(context, { id: 'category-layout-id' })).toBe(true);
        expect(methods.isDefaultLayout.call(context, { id: 'another-layout-id' })).toBe(false);
    });

    it('builds the page type label through isDefaultLayout, so extensions can add their own default layout', async () => {
        const methods = await getMethods();
        const context = {
            $t: (key) => key,
            cmsPageTypeService: {
                getType: () => ({ title: 'page-type-title' }),
            },
            // An extension adding its own default layout only has to override `isDefaultLayout`.
            isDefaultLayout: (page) => page.id === 'bundle-layout-id',
        };

        expect(methods.getPageType.call(context, { id: 'bundle-layout-id', type: 'bundle_detail' })).toBe(
            'sw-cms.components.cmsListItem.defaultLayout - page-type-title',
        );
        expect(methods.getPageType.call(context, { id: 'another-layout-id', type: 'bundle_detail' })).toBe(
            'page-type-title',
        );
    });

    it.each([
        'sw_cms_list_listing_list_item_option_set_as_default',
        'sw_cms_list_listing_list_data_grid_actions_set_as_default',
    ])('keeps the core item and appends the extension item when %s is overridden', (blockName) => {
        const html = renderWithOverrides([
            overrideBlock('plugin', blockName, '<sw-context-menu-item class="plugin-item" />'),
        ]);

        expect(html).toContain('sw-cms-list-item__option-set-as-default');
        expect(html).toContain('plugin-item');
    });

    it.each([
        [
            'before',
            0,
        ],
        [
            'after',
            1,
        ],
    ])(
        'renders an extension item added to the set as default block when it is registered %s another override',
        (_, pluginIndex) => {
            const otherIndex = pluginIndex === 0 ? 1 : 0;
            const overrides = [];

            overrides[pluginIndex] = overrideBlock(
                'plugin',
                'sw_cms_list_listing_list_item_option_set_as_default',
                '<sw-context-menu-item class="plugin-item" />',
            );
            overrides[otherIndex] = overrideBlock(
                'other',
                'sw_cms_list_listing_list_item_option_set_as_default',
                '<sw-context-menu-item class="other-item" />',
            );

            const html = renderWithOverrides(overrides);

            expect(html).toContain('sw-cms-list-item__option-set-as-default');
            expect(html).toContain('plugin-item');
            expect(html).toContain('other-item');
        },
    );
});
