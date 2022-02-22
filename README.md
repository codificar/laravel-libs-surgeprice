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
        "codificar/surgeprice": "1.0.0",
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
$ php artisan migrate
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

Add the existing regions based on requests/providers location (state):


> ![alt text](/region_example.jpg?raw=true)


## Quickstart

Run the following command to create the ML models for each region configured:

```shell
php artisan ml:train_models 
```
> **Note**: This command will exclude all existing surge areas and their respective surge history. 
> 
> It is recommended to run it periodically (**between 1x a month to 1x year**) on **all regions**, to detect possible new surge areas.


Schedule the following command to update the surge fare for each surge area defined by the ML models:

```shell
php artisan ml:predict_data
```
> **Note**: It is recommended to schedule this command with the **same periodicity** set in the **Configuration** step (update_surge_window).
