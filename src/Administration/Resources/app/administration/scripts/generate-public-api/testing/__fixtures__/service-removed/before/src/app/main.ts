/**
 * @sw-package framework
 */
import ExampleExportService from 'src/app/service/example-export.service';
import ExampleLegacyService from 'src/app/service/example-legacy.service';
import ExampleInternalService from 'src/app/service/example-internal.service';

const { Application } = Shopware;

Application.addServiceProvider('exampleExportService', () => {
    return new ExampleExportService();
})
    .addServiceProvider('exampleLegacyService', () => {
        return new ExampleLegacyService();
    })
    .addServiceProvider('exampleInternalService', () => {
        return new ExampleInternalService();
    });
