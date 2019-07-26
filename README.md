Services_Mailman
================

This version of Services_Mailman removes all pear dependencies, using guzzle for requests, gives the code a namespace.

It also  provides phpunit tests in place of the .phpt tests in the original package.

As with the original package, this allows the integration of Mailman into a dynamic website without using Python or requiring permission to Mailman binaries.

To use this, your code will need to require vendor/autoload.php.


Credits
-------
* based on original pear/Services_Mailman package by James Wade (https://github.com/pear/Services_Mailman)
* Based on code by [Richard Plotkin](http://www.richardplotkin.com/)
* Concept by [Mailman](http://wiki.list.org/pages/viewpage.action?pageId=4030567)