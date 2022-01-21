# Surge fare extension

Machine Learning lib for surge multiplier generator in mobility projects.

## Installation

Add in composer.json:

```php
"repositories": [
    {
        "type": "vcs",
        "url": "https://libs:ofImhksJ@git.codificar.com.br/laravel-libs/surgeprice.git"
    }
]
```

```php
require:{
        "codificar/surgeprice": "master@dev",
}
```

```php
"autoload": {
    "psr-4": {
        "Codificar\\SurgePrice\\": "vendor/codificar/surgeprice/src/"
    },
}
```

This package requires [Laravel MySQL Spatial extension 2.0](https://github.com/grimzy/laravel-mysql-spatial) as a dependency. In case your project already uses this dependency, be sure to update to the required version or later.

Update project dependencies:

```shell
$ composer update
```

Register the service provider in `config/app.php`:

```php
'providers' => [
  /*
   * Package Service Providers...
   */
  Codificar\SurgePrice\SurgePriceServiceProvider::class,
],
```

Publish public images:

```shell
$ php artisan vendor:publish --tag=surgeprice --force
```

Run the migrations:

```shell
$ php artisan vendor:migrate
```

Install python 3 required libs:
```
sudo apt install python3-pip

pip3 install -U pandas

pip3 install -U scikit-learn
```

## Configuration

Navigate to route **/surgeprice/** and ensure to set a valid sytem path for Machine Learning related files. Default path:

```shell
/var/tmp/surgeprice/
```