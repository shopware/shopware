/**
 * This module creates an proxy server. It is used in Shopware storefront for the
 * hot module replacement to allow the server to automatically detect if the hot mode
 * is activated or not.
 */

const { createServer, request } = require('http');
const { spawn } = require('child_process');

module.exports = function createProxyServer(userOptions) {
    // user options
    const fullUrl = userOptions.fullUrl;
    const appPort = userOptions.appPort;
    const proxyHost = userOptions.proxyHost;
    const proxyPort = userOptions.proxyPort;
    const proxyUrl = `${proxyHost}:${proxyPort}`;
    const originalHost = userOptions.originalHost;

    // Create the HTTP proxy
    const server = createServer((client_req, client_res) => {
        const requestOptions = {
            port: appPort,
            path: client_req.url,
            method: client_req.method,
            headers: {
                ...client_req.headers,
                host: `${originalHost}:${appPort}`,
                'hot-reload-mode': true,
                'accept-encoding': 'identity',
            },
        };

        // pipe a new request to the client request
        client_req.pipe(
            // request the data
            request(requestOptions, (response) => {
                // if content type is not text/html
                if (String(response.headers['content-type']).indexOf('text/html') === -1) {
                    // pipe the request to the client without modification
                    response.pipe(client_res, {  end: true });

                    // finish request
                    return;
                }

                // transform bitcode to readable utf8 text
                response.setEncoding('utf8');

                // collect all chunks
                let responseData = '';
                response.on('data', (chunk) => responseData += chunk);

                // when request is finished
                response.on('end', () => {
                    // replace original url with proxy url
                    const responseBody = responseData.replace(new RegExp(`${fullUrl}/`, 'g'), `${proxyUrl}/`);

                    // end the client response with sufficient headers
                    client_res.writeHead(response.statusCode, response.headers);
                    client_res.end(responseBody);
                });
            }),
            {  end: true }
        );

    }).listen(proxyPort);

    // open the browser with the proxy url
    openBrowserWithUrl(proxyUrl);

    return Promise.resolve({ server, proxyUrl });
};

function openBrowserWithUrl(url) {
    const childProcessOptions = {
        stdio: 'ignore',
        detached: true,
    };

    try {
        const start = (process.platform === 'darwin' ? 'open' : process.platform === 'win32' ? 'start' : 'xdg-open');
        const child = spawn(start, [url], childProcessOptions);

        child.on('error', error => {
            console.log('Unable to open browser! Details:');
            console.log(error);
        })
    } catch (ex) {
        console.log(ex);
    }
}
