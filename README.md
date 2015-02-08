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

Install
-------

Install and activate the package using the composer and call `./flow
help` for a list of all currently implemented commands for this package.
