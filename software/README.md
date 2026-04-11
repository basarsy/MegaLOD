# Software

This directory contains the MegaLOD web platform built on [Omeka S](https://omeka.org/s/).

## Structure

- **`omeka-s/`** — Full Omeka S application (core, modules, themes, config templates).
  - **`modules/AddTriplestore/`** — Custom MegaLOD module for GraphDB triplestore integration.
  - **`themes/myTheme/`** — Custom MegaLOD theme.

## Setup

See the [root README](../README.md#getting-started) for installation steps.

### Requirements

- PHP 7.4+
- MySQL 5.7+ or MariaDB 10.2+
- Composer
- Web server (Apache / Nginx)

### Install Dependencies

```sh
cd omeka-s
composer install
```

`vendor/` and `node_modules/` are git-ignored. Always install dependencies from lockfiles after cloning.
