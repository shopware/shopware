/**
 * @sw-package discovery
 */
import 'src/module/sw-cms/mixin/sw-cms-state.mixin';

const { EntityCollection } = Shopware.Data;

async function getMethods() {
    const component = await wrapTestComponent('sw-cms-sidebar', {
        sync: true,
    });

    return component.methods;
}

function createPage() {
    const block = {
        id: 'block-id',
        sectionId: 'section-id',
    };
    const section = {
        id: 'section-id',
        blocks: new EntityCollection(null, 'cms_block', null, null, [block]),
    };

    return {
        block,
        page: {
            sections: new EntityCollection(null, 'cms_section', null, null, [section]),
        },
        section,
    };
}

describe('module/sw-cms/component/sw-cms-sidebar/delete', () => {
    beforeEach(() => {
        jest.spyOn(Shopware.Store, 'get').mockReturnValue({
            removeSelectedBlock: jest.fn(),
            removeSelectedSection: jest.fn(),
        });
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('does not save when removing a block from the editor', async () => {
        const methods = await getMethods();
        const { block, page, section } = createPage();
        const $emit = jest.fn();

        methods.onBlockDelete.call({ $emit, page, selectedBlock: block }, block);

        expect(section.blocks.has(block.id)).toBeFalsy();
        expect($emit).not.toHaveBeenCalled();
    });

    it('saves when removing a block from the navigator', async () => {
        const methods = await getMethods();
        const { block, page, section } = createPage();
        const $emit = jest.fn();
        const context = {
            $emit,
            onBlockDelete: methods.onBlockDelete,
            page,
            selectedBlock: block,
        };

        methods.onNavigatorBlockDelete.call(context, block, section);

        expect(section.blocks.has(block.id)).toBeFalsy();
        expect($emit).toHaveBeenCalledWith('page-save', true);
    });

    it('does not save when removing a section from the editor', async () => {
        const methods = await getMethods();
        const { page, section } = createPage();
        const $emit = jest.fn();

        methods.onSectionDelete.call({ $emit, page }, section.id);

        expect(page.sections.has(section.id)).toBeFalsy();
        expect($emit).not.toHaveBeenCalled();
    });

    it('saves when removing a section from the navigator', async () => {
        const methods = await getMethods();
        const { page, section } = createPage();
        const $emit = jest.fn();
        const context = {
            $emit,
            onSectionDelete: methods.onSectionDelete,
            page,
        };

        methods.onNavigatorSectionDelete.call(context, section.id);

        expect(page.sections.has(section.id)).toBeFalsy();
        expect($emit).toHaveBeenCalledWith('page-save');
    });
});
