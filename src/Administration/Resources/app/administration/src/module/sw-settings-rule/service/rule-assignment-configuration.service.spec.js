import createRuleAssignmentConfigService from 'src/module/sw-settings-rule/service/rule-assignment-configuration.service';

/**
 * @sw-package fundamentals@after-sales
 */
describe('src/module/sw-settings-rule/service/rule-assignment-configuration.service.js', () => {
    const configuration = createRuleAssignmentConfigService().getConfiguration();

    const configurationMap = Object.entries(configuration).map(
        ([
            key,
            entityConfig,
        ]) => ({
            key,
            gridColumns: entityConfig.gridColumns,
        }),
    );

    it('should contain configurations', () => {
        const expectedConfig = {
            product: {
                id: 'product',
            },
            shipping_method_availability_rule: {
                id: 'shipping_method_availability_rule',
            },
            shipping_method_prices: {
                id: 'shipping_method_prices',
            },
            tax_provider: {
                id: 'tax_provider',
            },
            payment_method: {
                id: 'payment_method',
            },
            promotion_order_rule: {
                id: 'promotion_order_rule',
            },
            promotion_customer_rule: {
                id: 'promotion_customer_rule',
            },
            promotion_cart_rule: {
                id: 'promotion_cart_rule',
            },
            promotion_discount_rule: {
                id: 'promotion_discount_rule',
            },
            promotion_group_rule: {
                id: 'promotion_group_rule',
            },
            flow: {
                id: 'flow',
            },
        };

        expect(configuration).toBeDefined();
        expect(Object.keys(configuration)).toHaveLength(Object.keys(expectedConfig).length);

        Object.keys(expectedConfig).forEach((key) => {
            const config = configuration[key];
            expect(config).toBeDefined();

            expect(config.id).toBe(expectedConfig[key].id);
        });
    });

    describe('column configuration', () => {
        it.each(configurationMap)('should use valid snippet keys for column labels in $key', ({ gridColumns }) => {
            gridColumns.forEach((col) => {
                expect(Shopware.Snippet.te(col.label)).toBeTruthy();
            });
        });

        it.each(configurationMap)('should have valid routerParameters structure in $key', ({ gridColumns }) => {
            gridColumns.forEach((col) => {
                if (!col.routerParameters) {
                    return;
                }

                col.routerParameters.forEach((param) => {
                    expect(typeof param.key).toBe('string');
                    expect(typeof param.path).toBe('string');
                });
            });
        });
    });
});
