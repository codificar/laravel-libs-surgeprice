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
        "codificar/surgeprice": "master",
}
}
```

```php
psr-4:{
    "Codificar\\SurgePrice\\": "vendor/codificar/surgeprice/src/",
}
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
