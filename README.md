# Vue-Storefront Connector Extension for OpenCart
**Compatible with: OpenCart 2.3.0.2**

**API Base URL:** `https://site_url/vsbridge/`

**API Credentials:**

- Username: OC API Name
- Password: OC API Key
- Secret Key: Must be generated in VC Bridge module settings
- Token Format: JWT

**Installation:**

* Add this composer package to your OpenCart project (extension files will be automatically copied):
```composer require ...```
* Add the URL rewrite rule for VSBridge (nginx example):
```nginx
location /vsbridge {
    rewrite ^/(.+)$ /index.php?route=$1 last;
}
```
* Install the extension in OpenCart (Extensions -> Modules) and generate a secret key

**Tests:**

* Edit `tests/test.php` and add the credentials and settings
* Run `php tests/test.php`
