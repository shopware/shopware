There are two filesystems for private and public purposes. They are meant for shared files like media or invoices that need to be available on every application server.

In addition, every installed and activated plugin gets its own space within your public or private filesystem. So, plugin developer don’t have to worry about existing files by other plugins.

-   The private namespace should be used for files, which **are not** accessable by the webroot like invoices or temporary files.
-   The public namespace should be used for files, which **are** accessable by the webroot like media files, assets, …

Usage
-----

Creating files is really easy. Just point to the file you want to write to and specify the contents or stream.:

    $filesystem = $this->container->get('shopware.filesystem.private');
    $filesystem->write('path/to/file.pdf', $invoiceContents);
    // or using streams
    $filesystem->writeStream('path/to/file.pdf', $invoiceStream);

*Keep in mind, that you have to provide access to your files using a gateway controller.*

Imagine to provide a download for the created file above is simple too.:

    $filesystem = $this->container->get('shopware.filesystem.private');
    $filesystem->read('path/to/file.pdf', $invoiceContents);
    // or using streams
    $filesystem->readStream('path/to/file.pdf', $invoiceStream);

For a more detail overview of the filesystem api, please refer to [thephpleague/flysystem].

Prefixed plugin filesystems
---------------------------

Each installed and activate plugin gets its own prefixed filesystem. Imagine a plugin named SwagBonus, you can access the plugins filesystem using the following services:

    $filesystem = $this->container->get('swag_bonus.filesystem.public');
    // or private filesystem
    $filesystem = $this->container->get('swag_bonus.filesystem.private');

The file will be stored in the global Shopware filesystem prefixed with `pluginData/pluginName`, e.g. `pluginData/SwagBonus`:

    $global = $this->container->get('shopware.filesystem.private');
    $plugin->write('path/fo/file.pdf', $contents);
    // will be stored in `path/to/file.pdf`

    $plugin = $this->container->get('swag_bonus.filesystem.private');
    $plugin->write('path/fo/file.pdf', $contents);
    // will be saved in `pluginData/SwagBonus/path/to/file.pdf`

Using external services
-----------------------

You can choose where to store your files. By default, they will be stored on the application server where the script gets executed. There are 3 additional services supported out-of-the-box.

### Amazon Web Services

To save your files on AWS S3, you have to modify your `config.php` and overwrite the filesystem you want to replace.

The following example will store all `public` files on AWS S3:

    'filesystem' => [
        'public' => [
            'type' => 'amazon-s3',
            'config' => [
                'bucket' => 'your-s3-bucket-name',
                'region' => 'your-bucket-reg'
            ]
        ]
    ]