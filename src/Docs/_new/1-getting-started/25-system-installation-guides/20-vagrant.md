[titleEn]: <>(Vagrant VM)

If using docker is not an option for you, you might want to try to set up the platform through Vagrant. In order to get the Shopware Platform up and running you do not even have to check out the sources. Just do the following:

### Requirements

* [Vagrant](https://www.vagrantup.com/) v2.4 or later
* [VirtualBox](https://www.virtualbox.org/) in a Vagrant compatible version
* Git
* unused IP-Address `192.168.33.10`

### Starting it

Start by checking out the repository [located here](https://github.com/shopwareLabs/shopware-platform-vagrant)

```
> git clone git@github.com:shopwareLabs/shopware-platform-vagrant.git
> cd shopware-platform-vagrant
```

Now we just execute `vagrant up` and wait while Vagrant downloads a virtual box image, checks out the platform and configures everything:


```bash
> vagrant up
```

*Notice: This will take **a while** on first execution. But caches will be created and used on any futher `up` call.*

### Accessing it

Afterwards you have a fully fledged Shopware Platform up and running. Access it through your browser:

URL | UI
---- | --------
[http://192.168.33.10](http://192.168.33.10) | Storefront 
[http://192.168.33.10/admin](http://192.168.33.10/admin) | Administration

Or use the terminal and access the virtual machine via:

```bash
> vagrant ssh
> cd shopware-dev/
> bin/console
``` 

*Notice: You should regularly update the box by executing `vagrant provision` - this will **reset** the box to it's stock state*

### Developing with it

The Vagrant box fully encapsulates the whole platform with all its sources. So the development process works just like with any other foreign system. The machine supports **SCP** as a way to go:

Key |  Setting
----------|----------
**Host:** | `192.168.33.10`
**User:**  | `vagrant`
**Password:** | `vagrant`
**Path:** | `/~/shopware-dev`

### Next: [Startup](./../30-startup-guide/__categoryInfo.md)
