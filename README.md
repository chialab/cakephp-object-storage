# Object storage plugin for CakePHP

This plugin offers an implementation of an object storage for [CakePHP](https://cakephp.org) applications.

## Features

This plugin provides:
- migration for creating the `files` table
- entity and table classes for `Files` model
- REST controller
- default routing
- service provider for the storage service using CakePHP's [service container](https://book.cakephp.org/4/en/development/dependency-injection.html)
- event listener to inject the service container in table instance on initialization
- modeless forms to handle file operations
- shell command to cleanup incomplete multipart uploads

## Installation

You can install this plugin using [composer](https://getcomposer.org):
```shell
composer install chialab/cakephp-object-storage
```

To use AWS S3 as a backend storage, the SDK is also needed:
```shell
composer install aws/aws-sdk-php
```

## Usage

Add the plugin in your `Application.php` bootstrap:
```php
    public function bootstrap(): void
    {
        // ...

        $this->addPlugin('Chialab/CakeObjectStorage');

        // ...
    }
```

Run the migration to create the `files` table:
```shell
bin/cake migrations migrate --plugin Chialab/CakeObjectStorage
```

Add the configuration for the backend storage in your `app.php`:
```php
    // ...

    'Storage' => [
        'className' => FilesystemAdapter::class,
        'args' => [
            WWW_ROOT . 'files' . DS,
            TMP . 'incomplete-uploads' . DS,
            '/files/',
            0007,
        ],
    ],

    // ...
```
See [`chialab/php-object-storage`](https://github.com/chialab/php-object-storage) library's README for more information on the adapters.

### Disable routing

Use `['routes' => false]` when adding the plugin to implement your own routes. You can still use `Chialab/CakeObjectStorage.Files` as controller
if you only want to change the paths, or implement your own controller.

### Disable event listener

Use `['bootstrap' => false]` when adding the plugin to disable automatically adding the event listener.
If you're using the plugin's `FilesTable`, you are required to set the DI container to its instances, or implement your own service handling.

### Disable service provider

Use `['services' => false]` when adding the plugin to disable automatically adding the storage service provider.
You are required to provide a `MultipartUploadInterface` implementation to `FilesTable` instances, or implement your own service handling.

## Upload

See `File::getMultipartChunkSize()` for the threshold between small file and multipart upload.

### Create file entity

The default controller accepts a request like the following:
```
POST /files

{
    "filename": "example.jpg",
    "mime_type": "image/jpg",
    "size": 20480 // 20 MiB
}
```

The response is like the following:
```json
{
    "file": {
        "filename": "example.jpg",
        "mime_type": "image/jpg",
        "size": 20480, // 20 MiB
        "created": "2023-05-08T15:58:27+00:00",
        "id": "076e11d0-4ba6-4680-8796-ee232eeca090",
        "is_multipart": true,
        "is_finalized": false,
        "url": null
    },
    "chunk_size": 10485760, // 10 MiB, present only if `is_multipart === true`
    "upload": "http://ossma.localhost/api/v1/files/076e11d0-4ba6-4680-8796-ee232eeca090/upload",
    "finalize": "http://ossma.localhost/api/v1/files/076e11d0-4ba6-4680-8796-ee232eeca090/finalize" // present only if `is_multipart === true`
}
```

The file entity in the response has a property `is_multipart` to let the client know if a multipart upload is required.
In this case, the response will also contain a `chunk_size` property with the maximum file part size, and a `finalize` URL
to call after all parts have been uploaded.

### Upload file

To upload the file, make a request like the following with the file as body:
```
POST /files/{file_id}/upload
```

### Multipart upload

To upload a part of a multipart upload, add the `part` query parameter to the request:
```
POST /files/{file_id}/upload?part=1
```
The parameter is an incremental number starting from 1, which represent the "index" of the part currently uploading.

The endpoint will respond with a hash of the uploaded part that needs to be stored to finalize the upload later:
```json
{
    "part": "1",
    "hash": "86507bea6d332c2814df1d244abdd3696169607f5f42ac3f6782dd69883f6b0d"
}
```

To finalize the upload:
```
POST /files/{file_id}/finalize

{
    "hashes": [
        { "part": 1, hash: "86507bea6d332c2814df1d244abdd3696169607f5f42ac3f6782dd69883f6b0d" },
        { "part": 2, hash: "df7c1db235bac334dab81d0a25824055bdb738bf54fd2857698d4a9fd88af2c6" },
    ]
}
```

To abort a multipart upload:
```
DELETE /files/{file_id}/abort
```
