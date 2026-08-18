/**
 * @sw-package fundamentals@framework
 */
import { colonToDot, isPrivilegeGranted, computePrivilegeChips } from 'src/core/helper/mcp-privilege.helper';

describe('colonToDot', () => {
    it('converts read → viewer', () => {
        expect(colonToDot('product:read')).toBe('product.viewer');
    });

    it('converts update → editor', () => {
        expect(colonToDot('order:update')).toBe('order.editor');
    });

    it('converts create → creator', () => {
        expect(colonToDot('sales_channel:create')).toBe('sales_channel.creator');
    });

    it('converts delete → deleter', () => {
        expect(colonToDot('customer:delete')).toBe('customer.deleter');
    });

    it('returns null for unknown operation', () => {
        expect(colonToDot('product:unknown')).toBeNull();
    });

    it('returns null for dynamic chip placeholder', () => {
        expect(colonToDot('<entity>:read')).toBeNull();
    });

    it('returns null for nullish input instead of throwing', () => {
        expect(colonToDot(undefined)).toBeNull();
        expect(colonToDot(null)).toBeNull();
    });
});

describe('isPrivilegeGranted', () => {
    it('returns true when privilege is in colon format in the list', () => {
        expect(isPrivilegeGranted('sales_channel:read', ['sales_channel:read'])).toBe(true);
    });

    it('returns true when privilege is covered by dot-format role from General tab', () => {
        expect(isPrivilegeGranted('sales_channel:read', ['sales_channel.viewer'])).toBe(true);
        expect(isPrivilegeGranted('order:update', ['order.editor'])).toBe(true);
        expect(isPrivilegeGranted('product:create', ['product.creator'])).toBe(true);
        expect(isPrivilegeGranted('customer:delete', ['customer.deleter'])).toBe(true);
    });

    it('returns false when privilege is not in the list in any format', () => {
        expect(isPrivilegeGranted('order:create', ['sales_channel.viewer'])).toBe(false);
        expect(isPrivilegeGranted('order:create', [])).toBe(false);
    });

    it('returns false for dynamic chip placeholders that have no matching entry', () => {
        expect(isPrivilegeGranted('<entity>:read', [])).toBe(false);
    });

    it('returns false for nullish chips instead of throwing', () => {
        expect(isPrivilegeGranted(undefined, ['product.viewer'])).toBe(false);
        expect(isPrivilegeGranted(null, ['product.viewer'])).toBe(false);
    });
});

describe('computePrivilegeChips', () => {
    it('returns an empty list for nullish required privileges', () => {
        expect(computePrivilegeChips(null)).toStrictEqual([]);
        expect(computePrivilegeChips(undefined)).toStrictEqual([]);
    });

    it('drops nullish static privilege entries', () => {
        expect(
            computePrivilegeChips({
                static: [
                    'product:read',
                    undefined,
                    null,
                    'order:update',
                ],
            }),
        ).toStrictEqual([
            'product:read',
            'order:update',
        ]);
    });

    it('expands entity/operation pairs into dynamic chips', () => {
        expect(
            computePrivilegeChips({
                static: ['product:read'],
                entityParam: 'entity',
                operations: [
                    'read',
                    'update',
                ],
            }),
        ).toStrictEqual([
            'product:read',
            '<entity>:read',
            '<entity>:update',
        ]);
    });
});
