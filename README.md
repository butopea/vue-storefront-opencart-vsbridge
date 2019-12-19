# Vue Storefront Connector Extension for OpenCart
**Compatible with: OpenCart 2.3.0.2**

**API Base URL:** `https://site_url/vsbridge/`

**API Credentials:**

- Username: OC API Name
- Password: OC API Key
- Secret Key: Must be generated in VS Bridge module settings
- Token Format: JWT

**Installation:**

* Add the following line in the extra section of your OpenCart's composer.json:

**Make sure to change the destination folder to match your upload/public folder.**
```json
"extra": {
        "filescopier": [
            {
                "source": "vendor/butopea/vue-storefront-opencart-vsbridge/src",
                "destination": "upload",
                "debug": "true"
            }
        ]
    }    
```

Notes about the `source` and `destination` paths:

> * The destination element must be a folder. if the destination folder does not exists, it is recursively created using `mkdir($destination, 0755, true)`
> * If the destination folder is not an absolute path, the relative path is calculated using the vendorDir path (`$project_path = \realpath($this->composer->getConfig()->get('vendor-dir').'/../').'/'`;)
> * The source element is evaluated using the php function `\glob($source, GLOB_MARK)` and a recursive copy is made for every result of this function into the destination folder

* Run the following command to add the required composer packages, including the VS Bridge itself:
```bash
composer require butopea/vue-storefront-opencart-vsbridge
```

* Add the URL rewrite rule for VS Bridge (Nginx example):
```nginx
location /vsbridge {
    rewrite ^/(.+)$ /index.php?route=$1 last;
}
```
* Install the extension in OpenCart (Extensions -> Modules) and generate a secret key

This extension will create its own tables in the database. An overview of the database changes [can be found here](https://github.com/butopea/vue-storefront-opencart-vsbridge/blob/master/src/admin/controller/extension/module/vsbridge.php#L103).

* Get the [Vue Storefront OpenCart Indexer](https://github.com/butopea/vue-storefront-opencart-indexer) to import your data into ElasticSearch.

*You need to whitelist your indexer's IP address in OpenCart at oc_url/admin/index.php?route=user/api*

**Tests:**

* Edit `tests/test.php` and add the credentials and settings
* Run `php tests/test.php`

**Development:**

We're currently in the early stages of getting all the features working and would love other OpenCart developers to join in with us on this project! 

If you found a bug or want to contribute toward making this extension better, please fork this repository, make your changes, and make a pull request.  

**Credits:**

Made with ❤ by [Butopêa](https://butopea.com)

**Support:**


Please ask your questions regarding this extension on Vue Storefront's Slack https://vuestorefront.slack.com/ You can join via [this invitation link]().

**License:**

This extension is completely free and released under the MIT License.
