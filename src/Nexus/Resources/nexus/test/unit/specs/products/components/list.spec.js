import ComponentFactory from 'src/core/factory/component.factory';

require('src/app/product/list');

const comp = ComponentFactory.getComponentRegistry().get('list');

describe('products/components/list.vue', () => {
    it('has a created hook', () => {
        expect(comp.created).to.be.a('function');
    });

    it('sets the correct default data', () => {
        expect(comp.data).to.be.a('function');
        const data = comp.data();
        expect(data.productList).to.be.an('array');
        expect(data.total).is.equal(0);
    });
});
