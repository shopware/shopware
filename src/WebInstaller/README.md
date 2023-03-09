# Web Installer

The web installer is a simple Symfony application packaged as a Phar file, that allows running automated Composer commands to install or update Shopware.
The term installer means 

## Create a Phar file

To create a Phar file, first install the dependencies with Composer:

    composer install

Then run the following command:

    composer run build-pha

## Running Unit Tests

To run the unit tests, use the following command:

    composer run test

## Running the Web Installer

Copy the created `shopware-installer.phar.php` file to the root directory of your Shopware installation or into an empty directory.

Request that page in your browser with /shopware-installer.phar.php and the Installer will decide if you need to install or update Shopware.

## Running the Web Installer in Development Mode

For development first set up a second Shop installation inside the `shop` directory, to set up this installation run `composer run e2e:web-update:prepare`.

Then start a second Webserver for only this Shop with `composer run e2e:web-update:start`. 
The Web installer will be available at http://localhost:8050/shopware-installer.phar.php. 
It is recommended to start the watcher when you are changing the Web Installer code with `watch-updater`.

## Running update against an unreleased Shopware version



