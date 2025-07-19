import AddressSearchPlugin from 'src/plugin/address-search/address-search.plugin';
/**
 * @package checkout
 */
describe('AddressSearchPlugin test', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        jest.useRealTimers();
    });

    test('plugin initializes', () => {
        const addressSearch = create();

        expect(typeof addressSearch).toBe('object');
        expect(addressSearch).toBeInstanceOf(AddressSearchPlugin);
    });

    test('test no available addresses', () => {
        create();

        expect(document.querySelector('.address-manager-no-address').classList).toContain('d-none');
        expect(document.querySelector('.address-manager-empty-address').classList).not.toContain('d-none');
    });

    test('test no addresses not found', () => {
        const addressSearch = create();

        const mockEvent = {
            target: {
                value: 'search',
            },
        };

        addressSearch._onSearch(mockEvent);

        expect(document.querySelector('.address-manager-no-address').classList).not.toContain('d-none');
        expect(document.querySelector('.address-manager-empty-address').classList).toContain('d-none');
    });

    test('hide empty state', () => {
        const addressSearch = create(true);

        const mockEvent = {
            target: {
                value: 'address',
            },
        };

        addressSearch._onSearch(mockEvent);

        expect(document.querySelector('.address-manager-no-address').classList).toContain('d-none');
        expect(document.querySelector('.address-manager-empty-address').classList).toContain('d-none');
    });
});

function create(withAddresses = false) {
    document.body.innerHTML = `
        <div class="address-manager-list-base mb-3" data-address-search="true">
            <input
                type="search"
                class="form-control address-manager-list-search"
                autocomplete="address"
                placeholder="search"
                name="search"
                value=""       
            />
            <ul class="list-unstyled row g-3 address-manager-list-wrapper">
                ${withAddresses ? '<div><div class="address-manager-select-address">address 1</div></div> <div><div class="address-manager-select-address">address 2</div></div> ' : ''}
            </ul>
            
            <p class="address-manager-no-address d-none">
                No addresses found.
            </p>

            <p class="address-manager-empty-address d-none">
                No available address found.
            </p>
        </div>
   `;


    return new AddressSearchPlugin(document.querySelector('.address-manager-list-base'));
}

