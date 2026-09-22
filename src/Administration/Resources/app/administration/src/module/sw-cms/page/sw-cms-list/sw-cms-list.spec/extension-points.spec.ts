/**
 * @sw-package discovery
 */
import TemplateFactory from 'src/core/factory/template.factory';
import template from 'src/module/sw-cms/page/sw-cms-list/sw-cms-list.html.twig';

interface CmsPageStub {
    id: string;
    type?: string;
}

interface BlockOverride {
    name: string;
    blockName: string;
    raw: string;
}

/**
 * The part of the `sw-cms-list` contract this suite drives directly. Both methods are called with an
 * explicit `this`, so the declared context is exactly what an extension has to provide when it
 * overrides `isDefaultLayout`.
 */
interface CmsListMethods {
    isDefaultLayout(
        this: {
            defaultProductId: string;
            defaultCategoryId: string;
        },
        page: CmsPageStub,
    ): boolean;
    getPageType(
        this: {
            $t: (key: string) => string;
            cmsPageTypeService: { getType: (type?: string) => { title: string } };
            isDefaultLayout: (page: CmsPageStub) => boolean;
        },
        page: CmsPageStub,
    ): string;
}

/** Every template a test registered, so `afterEach` can drop it from the registries again. */
const registeredTemplates: string[] = [];

async function getMethods(): Promise<CmsListMethods> {
    const component = (await wrapTestComponent('sw-cms-list', {
        sync: true,
    })) as { methods: CmsListMethods };

    return component.methods;
}

/**
 * Renders the real `sw-cms-list` template together with the given overrides, the same way the
 * template factory merges plugin overrides at runtime.
 *
 * The registry key carries the overridden block names, so every case renders from a registration of
 * its own: `registerComponentTemplate()` keeps the overrides already stored under a key, so cases
 * sharing a key would inherit each other's overrides and stop proving anything about their block.
 */
function renderWithOverrides(overrides: BlockOverride[]): string {
    const name = `sw-cms-list-extension-points-${overrides
        .map((override) => `${override.name}-${override.blockName}`)
        .join('-')}`;

    TemplateFactory.registerComponentTemplate(name, template);
    overrides.forEach((override, index) => {
        TemplateFactory.registerTemplateOverride(name, override.raw, index);
    });
    registeredTemplates.push(name);

    const html = TemplateFactory.getRenderedTemplate(name);

    // `getRenderedTemplate()` returns null for a template it cannot resolve, which would turn every
    // `toContain()` below into the same unhelpful failure.
    if (html === null) {
        throw new Error(`The template "${name}" could not be rendered.`);
    }

    return html;
}

function overrideBlock(name: string, blockName: string, markup: string): BlockOverride {
    return {
        name,
        blockName,
        raw: `{% block ${blockName} %}{% parent %}${markup}{% endblock %}`,
    };
}

describe('module/sw-cms/page/sw-cms-list/extension-points', () => {
    afterEach(() => {
        registeredTemplates.forEach((name) => {
            TemplateFactory.getTemplateRegistry().delete(name);
            TemplateFactory.getNormalizedTemplateRegistry().delete(name);
        });
        registeredTemplates.length = 0;
    });

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
            $t: (key: string) => key,
            cmsPageTypeService: {
                getType: () => ({ title: 'page-type-title' }),
            },
            // An extension adding its own default layout only has to override `isDefaultLayout`.
            isDefaultLayout: (page: CmsPageStub) => page.id === 'bundle-layout-id',
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
    ] as const)(
        'renders an extension item added to the set as default block when it is registered %s another override',
        (_, pluginIndex) => {
            const otherIndex = pluginIndex === 0 ? 1 : 0;
            const overrides: BlockOverride[] = [];

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
