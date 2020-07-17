[titleEn]: <>(HttpClient)
[hash]: <>(article:administration_http_client)

We provide the HttpClient for fetching and sending data to the Api and external services. It is an
wrapper around the popular HTTP client Axios.

## Access the httpClient

You can access the httpClient from the global Shopware object: 
```javascript
const httpClient = Shopware.Application.getContainer('init').httpClient;
```

## Relevant functions

`httpClient.request(config)`
 : Send a request to the server

`httpClient.get(url[, config])`
 : Send a get request to the server

`httpClient.delete(url[, config])`
 : Send a delete request to the server

`httpClient.post(url[, data[, config]])`
 : Send a post request to the server

`httpClient.put(url[, data[, config]])`
 : Send a put request to the server
 
 `httpClient.patch(url[, data[, config]])`
  : Send a patch request to the server

## Config values

You can change several configurations for each request:

```javascript
const config = {
  // Here you can set the version for the api. For backward compatibility we use the latest
  // supported version. Therefore you have to manually set the newest version if you need the
  // latest api features.
  // default is the latest supported version
  version: 2,

  // `url` is the server URL that will be used for the request
  url: '/search/product',

  // `method` is the request method to be used when making the request
  method: 'get', // default

  // `baseURL` will be prepended to `url` unless `url` is absolute.
  // We set the `baseURL` for an instance of httpClient. Therefore you do not
  // need to change the `baseURL` in the most cases.
  baseURL: 'https://shopware.test.com/api/v1',

  // `headers` are custom headers to be sent
  headers: {'X-Requested-With': 'XMLHttpRequest'},

  // `params` are the URL parameters to be sent with the request
  // Must be a plain object or a URLSearchParams object
  params: {
    ID: 12345
  },

  // `data` is the data to be sent as the request body
  // Only applicable for request methods 'PUT', 'POST', and 'PATCH'
  // It must be of one of the following types:
  // - string, plain object, ArrayBuffer, ArrayBufferView, URLSearchParams, FormData, File, Blob
  data: {
    name: 'Example product name'
  },

  // `timeout` specifies the number of milliseconds before the request times out.
  // If the request takes longer than `timeout`, the request will be aborted.
  timeout: 1000, // default is `0` (no timeout)

  // `onUploadProgress` allows handling of progress events for uploads
  // browser only
  onUploadProgress: function (progressEvent) {
    // Do whatever you want with the native progress event
  },

  // `onDownloadProgress` allows handling of progress events for downloads
  // browser only
  onDownloadProgress: function (progressEvent) {
    // Do whatever you want with the native progress event
  },

  // `validateStatus` defines whether to resolve or reject the promise for a given
  // HTTP response status code. If `validateStatus` returns `true` (or is set to `null`
  // or `undefined`), the promise will be resolved; otherwise, the promise will be
  // rejected.
  validateStatus: function (status) {
    return status >= 200 && status < 300; // default
  },
  // `cancelToken` specifies a cancel token that can be used to cancel the request
  cancelToken: new CancelToken(function (cancel) {
  })
}
```
