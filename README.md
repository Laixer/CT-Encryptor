# Encryptor

The Encryptor package can manage files and resources in a database-like manner. Files are stored as objects and can optionally be encrytped. Automatic management should make a resource table obsolete since the objects are standalone. The Encryptor package is a dropin replacement for the Laravel provided Storage facade.

## Requirements ##
To use the Bynq Encryptor, the following is required:

+ Laravel 5.x.
+ Ramsey/UUID package
+ PHP >= 5.6
+ PHP Fileinfo extension
+ Up-to-date OpenSSL (or other SSL/TLS toolkit)

## Installation ##

Add the package to composer.json

	    {
	        "require": {
	            "bynqio/encryptor": "dev-master"
	        }
	    }

## Examples ##

Without encryption and application managed.

```php
	Encryptor::put('filename', 'contents');
```

To enable encryption set an encryption key.

```php
	Encryptor::put('filename', 'contents', 'secret');
```

Automatic file management.

```php
	Encryptor::putAuto('path', 'extension', 'contents');
	Encryptor::putAuto('path', 'extension', 'contents', 'secret');
```

Other file options:

```php
	Encryptor::size($file);
	Encryptor::delete($file);
	Encryptor::mimeType($file);
	Encryptor::lastModified($file);
```

## License ##
Copyright (C) 2017 Bynq.io B.V.
All rights reserved.

Content can not be copied and/or distributed without the express
permission of the author.