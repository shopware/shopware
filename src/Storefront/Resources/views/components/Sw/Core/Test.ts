import { ShopwareComponent } from 'shopware';

export default class TestComponent extends ShopwareComponent {
    init(): void {
        console.log('TestComponent initialized');
    }
}