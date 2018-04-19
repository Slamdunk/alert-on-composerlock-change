# Alert on composer.lock change...
### ...when commanding a `git pull`, `git checkout` or a `git merge`

[![Build Status](https://travis-ci.org/Slamdunk/alert-on-composerlock-change.svg?branch=master)](https://travis-ci.org/Slamdunk/alert-on-composerlock-change)
[![Packagist](https://img.shields.io/packagist/v/slam/alert-on-composerlock-change.svg)](https://packagist.org/packages/slam/alert-on-composerlock-change)

Show an alert when `composer.lock` changed while moving between commits.

![alert GIF](https://github.com/Slamdunk/alert-on-composerlock-change/raw/master/alert.gif)

## Installation

To use this extension, require it in [Composer](https://getcomposer.org/):

```bash
composer require --dev slam/alert-on-composerlock-change
```

## WARNING: git hooks overridden !

To enable the warning both `.git/hooks/post-merge` and `.git/hooks/post-checkout`
are overridden.

## Where to use it

This is useful in development, you clone the repo and you'll automatically
notified on `composer.lock` changes without custom hooks/code (after the
first `composer install` of course).

The alert is triggered also while moving between commits with `git checkout`.

## Where NOT to use it

You should avoid relying on this in production, as you are supposed to have a
dedicated strategy for deploy that involves much more than a plain `git pull`.

Also this isn't useful for a library, as libraries shouldn't commit the `composer.lock`.

## Why not just run `composer install`?

This is intended to help developers be aware of what happened in the repo while
they where sleeping (uh?).
Developers are supposed to investigate how the dependencies changed, to be aware
of them and, if needed, to discuss the changes and improve them.
If everything happens under the hood, knowledge would be much slower to gain.
