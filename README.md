# Shopware Project Next

[![Build Status](https://travis-ci.org/shopware/shopware.svg?branch=labs)](https://travis-ci.org/shopware/shopware)
[![Crowdin](https://d322cqt584bo4o.cloudfront.net/shopware/localized.svg)](https://crowdin.com/project/shopware)

- **License**: Dual license AGPL v3 / Proprietary
- **Github Repository**: <https://github.com/shopware/shopware>
- **Issue Tracker**: <https://issues.shopware.com>
- **Branch**: `labs`

### Shopware Server Requirements

- PHP 7.1 or above
- [Apache 2.2 or 2.4](https://httpd.apache.org/)
- Apache's `mod_rewrite` module
- MySQL 5.7.0 or above

#### Required PHP extensions:

-   <a href="http://php.net/manual/en/book.ctype.php" target="_blank">ctype</a>
-   <a href="http://php.net/manual/en/book.curl.php" target="_blank">curl</a>
-   <a href="http://php.net/manual/en/book.dom.php" target="_blank">dom</a>
-   <a href="http://php.net/manual/en/book.hash.php" target="_blank">hash</a>
-   <a href="http://php.net/manual/en/book.iconv.php" target="_blank">iconv</a>
-   <a href="http://php.net/manual/en/book.image.php" target="_blank">gd</a> (with freetype and libjpeg)
-   <a href="http://php.net/manual/en/book.json.php" target="_blank">json</a>
-   <a href="http://php.net/manual/en/book.mbstring.php" target="_blank">mbstring</a>
-   <a href="http://php.net/manual/en/book.openssl.php" target="_blank">OpenSSL</a>
-   <a href="http://php.net/manual/en/book.session.php" target="_blank">session</a>
-   <a href="http://php.net/manual/en/book.simplexml.php" target="_blank">SimpleXML</a>
-   <a href="http://php.net/manual/en/book.xml.php" target="_blank">xml</a>
-   <a href="http://php.net/manual/en/book.zip.php" target="_blank">zip</a>
-   <a href="http://php.net/manual/en/book.zlib.php" target="_blank">zlib</a>
-   <a href="http://php.net/manual/en/ref.pdo-mysql.php" target="_blank">PDO/MySQL</a>

### Installation via Git

Follow the instruction below if you want to install Shopware 5 using Git.

1.) Clone the git repository to the desired location using:

    git clone https://github.com/shopware/shopware.git

In case you wish to contribute to Shopware, fork the `labs` branch rather than cloning it, and create a pull request via Github. For further information please read the section "Get involved" of this document.

In case you want to use our docker image just type the following commands:
```yaml
./psh.phar docker:start

./psh.phar docker:ssh

./psh.phar init
```

Now your shop is available under the following url: http://10.101.101.56

If you don't want to use docker, please follow the next steps:

2.) Set the correct directory permissions:

    chmod -R 755 var
    chmod -R 755 web
    chmod -R 755 custom/plugins

Depending on your server configuration, it might be necessary to set whole write permissions (777) to the files and folders above.
You can also start testing with lower permissions due to security reasons (644 for example) as long as your php process can write to those files.

3.) Please configure your web server that the web directory is your root directory. At the moment we didn't support subdirectories.

4.) Copy the .psh.yaml.dist file to .psh.yaml.override and delete everything what is not part of the const section. Your override file should look like this:
```yaml
const:
  DB_USER: "app"
  DB_PASSWORD: "app"
  DB_HOST: "mysql"
  DB_NAME: "shopware"
  SW_HOST: "10.101.101.56"
  SW_BASE_PATH: ""
  PHP_VERSION: "7.1"
``` 
Please replace the provided credentials with your own. After that you can provision your installation via psh.phar:
```bash
./psh.phar init
```

You can now access your shop

# Backend

- **Requirements:**
    - Node.js > 8.x
    - NPM > 5.x

After initializing the application itself using `./psh.phar init` the stack is up and running. The backend is like a separate application with its own dependencies. Therefore we create a commands for your convince to set it up as well:

```bash
./psh.phar nexus:init
```

This will resolve the Node.js dependencies of the backend. If you're having trouble setting resolving the using `psh`, go to the `src/Nexus/Resources/nexus` directory and run `npm install` / `yarn` in the folder to resolve the dependencies manually.

Now you're having two ways to go. If you just want to have a working copy of the backend you have to build the project:

```bash
./psh.phar nexus:build
```

Now you can access the complied version of the backend using `<http://your-shop-url/nexus>`.

If you want to start developing with the backend we're highly recommend the hot module reloading mode. In this mode we're spawning a custom Node.js webserver which is using the `webpack-devserver`:

```bash
./psh.phar nexus:watch
```

The hot module reloading mode enables you to use the [Vue.js DevTools](https://chrome.google.com/webstore/detail/vuejs-devtools/nhdogjmejiglipccpnnnanhbledajbpd) as well as having hot module reloading in place for your components.

# Get involved

Shopware is available under dual license (AGPL v3 and proprietary license). If you want to contribute code (features or bugfixes), you have to create a pull request and include valid license information. You can either contribute your code under New BSD or MIT license.

If you want to contribute to the backend part of Shopware, and your changes affect or are based on ExtJS code, they must be licensed under GPL V3, as per license requirements from Sencha Inc.

If you are not sure which license to use, or want more details about available licensing or the contribution agreements we offer, you can contact us at <contact@shopware.com>.

For more information about contributing to Shopware, please see [CONTRIBUTING.md](CONTRIBUTING.md).


### How to report bugs / request features?

We've always had a sympathetic ear for our community, so please feel free to submit tickets with bug reports or feature requests. In order to have a single issue tracking tool, we've decided to close the GitHub issue panel in favor of our Jira issue tracker, which is directly connected to our development division.

* [Shopware Jira ticket submit form](https://issues.shopware.com)

# Copying / License

Shopware is distributed under a dual license (AGPL v3 and proprietary license). You can find the whole license text in the `license.txt` file.

# Changelog

The changelog and all available commits are located under <https://github.com/shopware/shopware/commits/labs>.

## Further reading

* [Shopware AG](http://www.shopware.com) - Homepage of shopware AG
* [Shopware Developer Documentation](https://devdocs.shopware.com/)
* [Shopware Community](http://community.shopware.com/) - Shopware Community
* [Shopware Forum](http://forum.shopware.com) - Community forum
* [Shopware Marketplace](http://store.shopware.com) - Shopware Store
* [Shopware on Crowdin](https://crowdin.com/project/shopware) - Crowdin (Translations)
