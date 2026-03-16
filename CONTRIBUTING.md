# Contributing to Laravel DMS Disk

Thank you for considering contributing! Every contribution is welcome and appreciated.

## Code of conduct

Please read and follow our [Code of Conduct](CODE_OF_CONDUCT.md).

## How to contribute

### Reporting bugs

Open a [GitHub issue](https://github.com/Arshad1114/laravel-dms-disk/issues) with:

- PHP version
- Laravel version
- Package version
- Steps to reproduce
- Expected vs actual behaviour
- Error message if any

### Suggesting features

Open a [GitHub issue](https://github.com/Arshad1114/laravel-dms-disk/issues) and describe:

- The problem you are trying to solve
- Your proposed solution
- Any alternatives you considered

### Submitting a pull request

1. Fork the repository
2. Create a feature branch:
```bash
   git checkout -b feat/your-feature-name
```
3. Make your changes
4. Write or update tests for your change
5. Make sure all tests pass:
```bash
   ./vendor/bin/phpunit
```
6. Commit using a clear message:
```bash
   git commit -m "feat: describe your change"
```
7. Push your branch:
```bash
   git push origin feat/your-feature-name
```
8. Open a pull request against the `main` branch

## Commit message format

Use these prefixes:

| Prefix | When to use |
|---|---|
| `feat:` | New feature |
| `fix:` | Bug fix |
| `docs:` | Documentation changes |
| `test:` | Adding or updating tests |
| `refactor:` | Code change that is not a fix or feature |
| `chore:` | Maintenance tasks |

## Development setup
```bash
git clone git@github.com:Arshad1114/laravel-dms-disk.git
cd laravel-dms-disk
composer install
./vendor/bin/phpunit
```

## Code style

This package follows PSR-12 coding standards. Keep your code clean and consistent with the existing codebase.

## Questions

Feel free to open an issue if you have any questions.
