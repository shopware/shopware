[titleEn]: <>(Vagrant VM)
[hash]: <>(article:vagrant_installation)

If using docker is not an option for you, vagrant is another great technology to quickly get a local Shopware up and running.

Other than with the docker or local setup, with vagrant you will have a complete separate server on your machine.

Because of technical reasons, the vagrant machine acts like a remote web server, so with this setup, you'll develop your code on your PC and then upload/synchronize it to the vagrant machine.

For this, the vagrant machine supports SCP/SSH, which is integrated in Editors like PhpStorm or Visual Studio Code.

## Requirements

* [Vagrant](https://www.vagrantup.com/) v2.2.4 or later
* [VirtualBox](https://www.virtualbox.org/) in a Vagrant compatible version
* [Git](https://git-scm.com/)
* [Vagrant Hostsupdater](https://github.com/cogitatio/vagrant-hostsupdater) (optional)

The IP address `192.168.33.10` is used by the vagrant box, so it must not be in use in the network already. If this is not possible, you manually have to change the IP address in the `Vagrantfile` you'll clone in the next step.

## Starting it

Start by cloning the repository [shopwareLabs/shopware-platform-vagrant](https://github.com/shopwareLabs/shopware-platform-vagrant)

```
> git clone git@github.com:shopwareLabs/shopware-platform-vagrant.git
> cd shopware-platform-vagrant
```

Next, execute `vagrant up` and wait while Vagrant downloads a virtual box image, clones the Shopware platform code and configures the server.

```bash
> vagrant up
```

*Note: This will take **quite a while** on first execution. But caches will be created and used on any further `up` call.*

## Advanced Setup

If you would like to access the Shopware instance using a hostname, rather than the IP address, you can enable the reverse proxy and - if you like - SSL encryption.

Both proxy and SSL can be enabled by editing the `ansible/vars/all.yml`. In this file, you will find the following options and be able to modify them accordingly.

Variable | Type | Default | Description
----|----|----|----
proxy_enabled | Boolean (yes/no) | no | Enables the installation of nginx as a reverse proxy
proxy_hostname | Hostname | "shopware.local" | Defines the hostname that will be used to access the Shopware instance
proxy_ssl | Boolean (yes/no) | no | Enables SSL

Please notice that you will have to modify your hosts file or use the Vagrant Hostsupdater plugin, when using a reverse proxy setup.

Given the hosts entry is set, you can access the Shopware instance via `https://<proxy_hostname>`, whereas `<proxy_hostname>` is a placeholder for the configured hostname (shopware.local per default).


## Accessing Shopware

After executing the `vagrant up` command you have a fully fledged Shopware 6 up and running. Access it through your browser.


Basic setup ( ansible vars: `proxy_enabled = no`, `proxy_hostname = shopware.local`, `proxy_ssl = no` ):

URL | UI
---- | --------
[http://192.168.33.10](http://192.168.33.10) | Storefront 
[http://192.168.33.10/admin](http://192.168.33.10/admin) | Administration


Advanced proxy setup ( ansible vars: `proxy_enabled = yes`, `proxy_hostname = shopware.local`, `proxy_ssl = yes`, local hosts file modified or Vagrant Hostupdater plugin in use ):

URL | UI
---- | --------
[https://shopware.local](https://shopware.local) | Storefront 
[https://shopware.local/admin](https://shopware.local/admin) | Administration

Or use the terminal and access the virtual machine via:

```bash
> vagrant ssh
> cd shopware-dev/
> bin/console
``` 

*Note: You should regularly update the box by executing `vagrant provision` - this will **reset** the box to it's stock state* meaning **Content inside the box is wiped and deleted**

## Connecting your IDE

The Vagrant box fully encapsulates the whole Shopware 6 with all its sources. So the development process works just like with any other foreign system. The machine supports **SCP** with the following credentials.

Key |  Setting
----------|----------
**Host:** | `192.168.33.10`
**User:**  | `vagrant`
**Password:** | `vagrant`
**Path:** | `~/shopware-dev`
