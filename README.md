# tollwerk TYPO3 console commands

[![TYPO3](https://img.shields.io/badge/TYPO3-12.4-orange.svg)](https://get.typo3.org/version/12)
[![GNU General Public License](https://img.shields.io/badge/License-GPL%203%20or%20later-lightgray.svg)](https://www.gnu.org/licenses/gpl-3.0.en.html)

This extension provides useful [Symfony](https://symfony.com/) console commands for TYPO3.

## Installation

Install the extension with composer.

```
composer require tollwerk/tw-console
```

## Available commands

---
### twconsole:slugs:update

Update slugs of a given table. Without any arguments or options, empty `pages.slug` fields will be updated.

#### Usage

`twconsole:slugs:update [options] [--] [<tableName> [<fieldName>]]`

#### Arguments

- `tableName` The table name [default: "pages"]
- `fieldName` The slug field name [default: "slug"]

#### Options

- `--uid="[UID]` Only update records with the given UIDs (multiple values allowed)
- `--pid="[PID]`  Only update records with the given PIDs and all children PIDs recursively (multiple values allowed)
- `-d, --dry-run` Preview all changes but do not update the actual data
- `-f, --force` If set, non empty slugs will be updated as well
- `-h, --help` Display help for the given command. When no command is given display help for the list command
- `--silent` Do not output any message
- `-q, --quiet` Only errors are displayed. All other output is suppressed
- `-V, --version` Display this application version
- `--ansi|--no-ansi` Force (or disable --no-ansi) ANSI output
- `-n, --no-interaction` Do not ask any interactive question
- `-v|vv|vvv, --verbose` Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
---
