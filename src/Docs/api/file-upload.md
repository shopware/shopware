# File Upload API

To Upload a File you first have to create a Media-Entity with a POST-Request to `api/media` with
```json
{
   "name": "fancyImage.jpg",
   "albumID": "ffffffff"
}
```
This is the minimal required Payload to create a Media-Entity.

If you have created a Media Entity you can upload your file via `api/media/{mediaId}/actions/upload`.
You have two options to upload your files:

1. Upload binary

You can POST your file you want to upload directly to the upload-route.
Be aware that you have to set the correct `Content-Type`- and `Content-Length`-headers

2. Fetch file from URL

If you want to upload a file that is already reachable via an url (e.g. from your own CDN) you can simply post the url to the upload-route.
```json
{
    "url": "my.cdn.com/fancyImage.jpg"
}
```

## Why don't use a multipart request
We decided to not use multipart request because of the developer experience. 
Not every programming language and platform supports multipart the same way. 
Additional reasons contain that the requests look messy, are had to document and even harder to test.