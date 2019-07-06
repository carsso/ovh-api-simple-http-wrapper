OVH API HTTP wrapper written in PHP
===================================

This HTTP wrapper will allow you to easily make OVH API calls by abstracting authentication and requests signature.

With this wrapper, your api calls will be as simple as `curl http://mywebsite.com/my-ovh-api-endpoint/me` (no token to log-in, no signature to handle..)

_Depends on the official OVH API PHP wrapper : https://github.com/ovh/php-ovh_

How to use
----------

Install dependencies with Composer : `composer install`

Copy the default htaccess file : cp .htaccess-dist .htaccess

Copy the default config file : `cp config.php-dist config.php`

Generate OVH API keys "script credentials" and update the config file accordingly : `vim config.php`.

_Read https://github.com/ovh/php-ovh#supported-apis to find the URL to generate the script credentials and the supported endpoints_

/!\ Security warning /!\
------------------------

This wrapper does not provide client-side authentication or restrictions of any kind.

That's a really convenient tool for testing and developing some small projects based on the OVH API, but, for security reasons, you should add an authentication layer in front of it and you should not expose it directly with access to your OVH account.

This wrapper is provided "as-is" and I decline any responsibility of any security issue with your OVH account if you choose to use it in an inappropriate/insecure way.

License
-------

BSD 3-clause "New" or "Revised" License


Note
----

This project is not affiliated with OVH.
