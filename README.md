# Asinius

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Asinius is a personal hobby-project. I'm gathering up a lot of my PHP snippets, library functions and classes, and other bits and pieces from many different projects into a loosely-coupled utility library that follows modern PHP development practices. I'm mirroring this copy on GitHub from my personal Phabricator server. There's no reason for anybody else to use this, but feel free to have a look around or offer suggestions and pull requests.

"Asinius" is a reference to [https://eaglesanddragonspublishing.com/gaius-asinius-pollio-and-the-first-public-library-in-ancient-rome/](the first public library), which clearly this isn't. But it's a fun name and historical reference anyway.

This is not intended to be, and will never become, a framework. This is an exploration of the old-school library architecture, recast for modern development.

## Asinius Core

This is the library "core". It currently includes an autoloading "polyfill" function for anybody who hasn't adopted Composer yet. You should be using Composer, but if you aren't, `require_once()` the `src/Asinius/autoload.php` file and the library will be able to autoload its classes using the same PSR-4 pattern that Composer uses. The included autoloader is pretty efficient and won't add much execution overhead.

## License

All of the Asinius project and its related modules are being released under the MIT License. See LICENSE.
