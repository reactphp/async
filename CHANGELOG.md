# Changelog

## 2.0.0 (2022-07-11)

A major new feature release, see [**release announcement**](https://clue.engineering/2022/announcing-reactphp-async).

*   We'd like to emphasize that this component is production ready and battle-tested.
    We plan to support all long-term support (LTS) releases for at least 24 months,
    so you have a rock-solid foundation to build on top of.

*   The v4 release will be the way forward for this package. However, we will still
    actively support v3 and v2 to provide a smooth upgrade path for those not yet
    on PHP 8.1+. If you're using an older PHP version, you may use either version
    which all provide a compatible API but may not take advantage of newer language
    features. You may target multiple versions at the same time to support a wider range of
    PHP versions:

    * [`4.x` branch](https://github.com/reactphp/async/tree/4.x) (PHP 8.1+)
    * [`3.x` branch](https://github.com/reactphp/async/tree/3.x) (PHP 7.1+)
    * [`2.x` branch](https://github.com/reactphp/async/tree/2.x) (PHP 5.3+)

This update involves some major changes over the previous `v1.0.0` release that
has been deprecated since 2013. Accordingly, most consumers of this package
should not be affected by any BC breaks. See below for more details:

*   Feature / BC break: Change to Promise-based APIs instead of callbacks (continuation-passing style).
    Support promise cancellation and upcoming Promise v3.
    (#6, #7, #9 and #46 by @clue)

*   Feature: Add new `await()` function (import from clue/reactphp-block).
    (#8 by @clue and #39 by @SimonFrings)

*   Minor documentation improvements.
    (#38 by @SimonFrings and #53 by @nhedger)

*   Improve test suite and add `.gitattributes` to exclude dev files from exports.
    Run tests on PHP 8.1, PHPUnit 9, switch to GitHub actions and clean up test suite.
    (#2, #3, #4, #5 and #10 by @clue)

## 1.0.0 (2013-02-07)

* First tagged release
