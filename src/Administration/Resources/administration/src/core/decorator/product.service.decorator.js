export default function ProductServiceDecorator(httpClient, productService) {
    console.group('product-service.decorator');
    console.log('httpClient', httpClient);
    console.log('productService', productService);
    console.groupEnd('product-service.decorator');
}
