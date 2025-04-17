# Translation Sync for Laravel

A simple Laravel package to extract translation keys used in your app and sync them into one or more language JSON files (e.g., `lang/it.json`).

---

## 📦 Installation

```bash
composer require trinavo/translation-sync --dev
```

---

## ⚙️ Configuration

First, publish the package configuration:

```bash
php artisan vendor:publish --tag=translation-sync-config
```

This will create a config file at:

```
config/translation-sync.php
```

In this file, set the path(s) to your translation JSON files:

```php
return [
    'lang_files' => [
        resource_path('lang/it.json'), // laravel < 12
        base_path('lang/ar.json'), // laravel 12+
    ],
];
```

---

## 🚀 Usage

Once you've configured the paths, run the following command:

```bash
php artisan translations:sync
```

This will:

- Scan your `app/` and `resources/` directories for any usage of:
  - `__('...')`
  - `trans('...')`
  - `@lang('...')`
- Collect all found keys.
- Merge them into the specified language JSON file(s).
- Preserve existing values and sort them alphabetically.

---

## 📁 Example Output

If your app contains:

```php
__('Welcome');
@lang('Logout');
```

Then `lang/ar.json` will be updated to include:

```json
{
    "Logout": "",
    "Welcome": ""
}
```

You can then update the values as needed for translation.

---


---

## 📝 License

This package is open-sourced software licensed under the [MIT license](LICENSE).

---

Made with ❤️ by Feras AbdAlrahman  
[doonfrs@gmail.com](mailto:doonfrs@gmail.com)
