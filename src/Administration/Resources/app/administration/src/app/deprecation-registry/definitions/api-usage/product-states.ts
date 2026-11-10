/**
 * @sw-package framework
 */

import { apiUsageMigration, componentMember, memberUsage, stringLiteral, templateAttribute, upgradeGuide } from '../helpers';

const MESSAGE =
    "Product states are removed in 6.8. Use product.type or productType ('physical' or 'digital') and creationType instead.";

export default apiUsageMigration({
    id: 'api.product-states',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The Administration uses the product type instead of the removed product states.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#product-states-replaced-by-the-product-type-in-the-administration'),
        upgradeGuide('UPGRADE-6.8.md#removal-of-products-states-field-in-favor-of-type-field'),
    ],
    usage: [
        componentMember({ components: ['sw-product-detail'], names: ['creationStates'], message: MESSAGE }),
        componentMember({
            components: [
                'sw-product-detail',
                'sw-product-detail-base',
                'sw-product-detail-specifications',
                'sw-product-variants-overview',
            ],
            names: ['productStates'],
            message: MESSAGE,
        }),
        componentMember({ components: ['sw-product-detail-variants'], names: ['currentProductStates'], message: MESSAGE }),
        componentMember({ components: ['sw-product-stream-value'], names: ['productStateOptions'], message: MESSAGE }),
        memberUsage({ store: 'swProductDetail', properties: ['productStates', 'creationStates'], message: MESSAGE }),
        memberUsage({ object: 'product', properties: ['states'], message: MESSAGE }),
        memberUsage({ object: 'this.product', properties: ['states'], message: MESSAGE }),
        stringLiteral({ values: ['is-physical', 'is-download', 'product-states-filter'], message: MESSAGE }),
        templateAttribute({ elements: ['sw-product-variants-overview'], names: ['product-states'], message: MESSAGE }),
    ],
});
