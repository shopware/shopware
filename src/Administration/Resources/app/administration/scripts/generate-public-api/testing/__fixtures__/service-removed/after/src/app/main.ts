/**
 * @sw-package framework
 */
import ExampleImportService from 'src/app/service/example-import.service';

const { Application } = Shopware;

Application.addServiceProvider('exampleImportService', () => {
    return new ExampleImportService();
});
