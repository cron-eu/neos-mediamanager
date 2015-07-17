CRON.MediaManager
=================

Abstract
--------

Sometimes, especially after playing around with dummy-content, the
media database gets messy.

This Neos-Package (for Neos 1.2.x) implements a command controller for
some cleanup-tasks like list, prune and garbage collect.

Known Limitations
-----------------

The Garbage Collect Task only works (currently) with image resources.

Install and Usage
-----------------

### Install

Because this package is not (now) available via
[Packagist](http://packagist.org), you have to setup the git-repo
accordingly in your `composer.json`:

```
    "repositories": [
		{
			"type": "git",
			"url": "https://github.com/cron-eu/neos-mediamanager.git"
		}
```

Then you can just install the package using composer:

```
composer require --no-update cron/neos-mediamanager:dev-master
composer install --no-dev
```

### Usage

Install and activate the package using the composer and call `./flow
help` for a list of all currently implemented commands for this package.
