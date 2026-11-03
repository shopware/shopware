import detailComponent from '../index';

const computed = (detailComponent as unknown as { computed: Record<string, (...args: unknown[]) => unknown> }).computed;

describe('Experience Studio layout assignment availability', () => {
    it('offers layout assignments only for saved product and category layouts', () => {
        expect(computed.canManageAssignments.call({ isCreateMode: false, layoutRootSource: 'product' })).toBe(true);
        expect(computed.canManageAssignments.call({ isCreateMode: false, layoutRootSource: 'category' })).toBe(true);
        expect(computed.canManageAssignments.call({ isCreateMode: true, layoutRootSource: 'category' })).toBe(false);
        expect(computed.canManageAssignments.call({ isCreateMode: false, layoutRootSource: 'landing_page' })).toBe(false);
        expect(computed.canManageAssignments.call({ isCreateMode: false, layoutRootSource: null })).toBe(false);
    });
});
