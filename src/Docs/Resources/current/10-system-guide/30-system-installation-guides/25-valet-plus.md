[titleEn]: <>(Valet+)
[hash]: <>(article:valet_plus_installation)

# How to setup Shopware 6 with Valet+ on OSX
Valet+ is a fork of [laravel/valet](https://github.com/laravel/valet). It supports automatic virtual host configuration based on the folder structure.

This is a modified version of the [official Installation Guide](https://github.com/weprovide/valet-plus/wiki/Installation).

## If you have Valet installed

1. Run `composer remove laravel/valet`

## Installing Valet-PHP

1. Update Homebrew via `brew update`
2. Add the Homebrew PHP tap for Valet+ via `brew tap henkrehorst/php`
3. Install PHP 7.4 using Homebrew via `brew install valet-php@7.4`
4. Link your PHP version using the `brew link valet-php@7.4 --force` command

## Installing Valet+

1. If needed, install composer via `brew instal composer`
2. Install Valet+ via `composer global require weprovide/valet-plus`
3. Make sure `~/.composer/vendor/bin` is in your path by adding `export PATH="$PATH:$HOME/.composer/vendor/bin"` to your `bash_profile` or `.zshrc`
4. Check for the following, common problem with `valet fix` WARNING: This will uninstall all other PHP installations
5. Run the `valet install` command. Optionally add `--with-mariadb` to use MariaDB instead of MySQL. This will configure and install Valet+ and DnsMasq. Additionally, it registers Valet's daemon to launch when your system starts

## Using Valet+ with Shopware 6

1. Create a new empty folder for example `~/sites`
2. Clone the development template like you normally would (dev + platform) into this folder
3. Run `./psh.phar install`
4. Move to `~/sites` and run `valet park` to register valet for this directory. Shopware should now be accessible via the `folder-name.test`. Notice: "folder-name" is the name of the shopware development template in `~/sites`
5. Optional: Disable SSL via `valet unsecure` because this might cause problems with the watcher

## Troubleshooting

### Testing your installation

1. Make sure `ping something.test` responds from 127.0.0.1.
2. Run `nginx -t` or `sudo nginx -t` and check for any errors.
	1. If there is a missing *elastisearch* file, follow "Missing Elasticsearch stub fix" further below

 ### Install Error: "*The process has been signaled with signal 9*"

 This is due to `valet fix` uninstalling `valet-php@7.4` for some reason. You can fix it by reinstalling Valet-PHP (Step 3 + 4 of "Installing Valet-PHP").
 Make sure to **NOT** run `valet fix` afterwards and just proceed with `valet install`
 
 ### Missing Elasticsearch stub fix

 ```bash
sudo cp ~/.composer/vendor/weprovide/valet-plus/cli/stubs/elasticsearch.conf /usr/local/etc/nginx/valet/elasticsearch.conf
```

```bash
valet domain test
```

### Watchers not working

Try disabing SSL via `valet unsecure`.
