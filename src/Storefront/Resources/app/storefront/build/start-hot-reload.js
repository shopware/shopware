const createLiveReloadServer = require('./live-reload-server/index');
const createProxyServer = require('./proxy-server-hot/index');

// starting the live reload server
const server = createLiveReloadServer();

server.then(() => {
    const fullUrl = process.env.APP_URL;
    // first value of array is the http protocol
    const domainWithPort = process.env.APP_URL.split('//')[1];
    const [ domain, port ] = domainWithPort.split(':');

    const proxyServerOptions = {
        fullUrl: fullUrl,
        appPort: port  || 80,
        proxyHost: 'http://localhost',
        proxyPort: 9998,
        // strip http:// and https://
        originalHost: domain,
    };

    // starting the proxy server
    createProxyServer(proxyServerOptions).then(({ proxyUrl } ) => {
        console.log('############');
        console.log(`Storefront proxy server started at ${proxyUrl}`);
        console.log('############');
        console.log('\n');
    });
});
