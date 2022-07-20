# php benchmark tests

Our php bench can not only be used to benchmark php code. We can also benchmark database related code.
To prevent manipulating the results, we implemented a ramp up logic within the `\Shopware\Tests\Bench\BenchExtension` class.

When php bench starts, we create a new database, reset the database and import a data set which can be defined in the `data.json` file inside this directory. 

## The data.json file
Within the `data.json` you can generate uuids via `{my-key}` and reference them in another json path. The generated ids are available in each php bench via `$this->ids`, when you extend from the base `\Shopware\Tests\Bench\BenchCase` class.

When you need some values from the `Defaults.php`, take a look into this function: `\Shopware\Core\Test\FixtureLoader::load`

We are not planning to implement some loop or random functions here. This kind of randomness would produce not comparable results. When you need a bunch of random data, please use the `faker` library within a small own script and export the generated json into the `data.json` file.

## Running the tests
To execute the php bench, simply run `./vendor/bin/phpbench run` in the root directory of the platform.

When you want a visual report of the tests, you can add the --report option: `./vendor/bin/phpbench run --report=compressed`

You can find the php bench documentation [here](https://phpbench.readthedocs.io/en/stable/).
