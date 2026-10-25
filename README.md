# SMF-TinyPortal

[![GitHub release](https://img.shields.io/github/release/Ezerous/SMF-TinyPortal.svg)](https://github.com/Ezerous/SMF-TinyPortal/releases)
[![SMF](https://img.shields.io/badge/SMF-1.1.x-blue.svg?style==flat)](https://simplemachines.org)
![Last Commit](https://img.shields.io/github/last-commit/Ezerous/SMF-TinyPortal/develop.svg?style=flat)

A package for [SMF](https://www.simplemachines.org/) v1.1.x forums that is a patched version of [TinyPortal](http://www.tinyportal.net) v1.0.0.

This version intends to extend the compatibility of the original package up to MySQL v5.7.

Compatibility fixes also cover the SMF1 article migration SQL, package uninstall language paths, and removed PHP runtime APIs used by TinyPortal's own code. The SMF1 branch intentionally retains SMF1's legacy database layer, so this is not a standalone PHP 8 port of SMF 1.1.x.

The original TinyPortal package version remains `1.107` for SMF package and upgrade compatibility. This fork uses [release-version.txt](release-version.txt) for its own semantic release versioning.

## License & Credits

This project is licensed under the Mozilla Public License 2.0 (MPL 2.0), just like the original TinyPortal.  
You can find the full text of the license in the [LICENSE](LICENSE) file or at [Mozilla’s official site](https://www.mozilla.org/MPL/2.0/).

All credits for the original package go to the TinyPortal Team.
