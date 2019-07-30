MailmanService
================

Version v1.0-beta removes all pear dependencies from pear/Services_Mailman, using guzzle for requests, gives the code a namespace.

It also  provides phpunit tests in place of the .phpt tests in the original package.

As with the original package, this allows the integration of Mailman into a dynamic website without using Python or requiring permission to Mailman binaries.

To use this, your code will need to require vendor/autoload.php.

master version uses symfony/browser-kit and symfony/http-client, modifies and incorporates some methods from https://github.com/ghanover/mailman-sync


Credits
-------
* some functionality based on code in https://github.com/ghanover/mailman-sync
* based on original pear/Services_Mailman package by James Wade (https://github.com/pear/Services_Mailman)
* Based on code by [Richard Plotkin](http://www.richardplotkin.com/)
* Concept by [Mailman](http://wiki.list.org/pages/viewpage.action?pageId=4030567)