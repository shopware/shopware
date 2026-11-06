/**
 * @sw-package framework
 */

import { componentMember, jsApiMigration, memberUsage, storeMember, stringValue, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.product-states',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Product states are replaced by the product type and the creation type.',
    references: [upgradeGuide('UPGRADE-6.8.md#product-states-replaced-by-the-product-type-in-the-administration')],
    usage: [
        componentMember({
            components: [
                'sw-product-detail',
                'sw-product-detail-base',
                'sw-product-detail-specifications',
                'sw-product-variants-overview',
            ],
            member: 'productStates',
            to: 'productType',
        }),
        componentMember({
            components: ['sw-product-detail-variants'],
            member: 'currentProductStates',
            to: 'currentProductType',
        }),
        componentMember({
            components: ['sw-product-stream-value'],
            member: 'productStateOptions',
            to: 'productTypeOptions',
        }),
        storeMember({ store: 'swProductDetail', member: 'productStates', to: 'productType' }),
        storeMember({ store: 'swProductDetail', member: 'creationStates', to: 'creationType' }),
        memberUsage({ from: '*.creationStates', to: 'creationType' }),
        memberUsage({ from: 'product.states', to: "product.type or productType ('physical' or 'digital')" }),
        memberUsage({ from: 'this.product.states', to: "product.type or productType ('physical' or 'digital')" }),
        stringValue({ from: 'is-physical', to: 'physical' }),
        stringValue({ from: 'is-download', to: 'digital' }),
        stringValue({ from: 'product-states-filter', to: 'product-type-filter' }),
    ],
});
