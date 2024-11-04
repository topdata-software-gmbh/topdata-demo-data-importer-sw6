# Rector Usage Instructions

Rector is a tool for instant upgrades and automated refactoring of your PHP code. This document provides basic instructions on how to use Rector in this project.

## Running Rector

1. To perform a dry run (see what would be changed without actually changing files):
   ```
   vendor/bin/rector process src --dry-run
   ```

2. To apply changes:
   ```
   vendor/bin/rector process src
   ```

3. To process a specific file or directory:
   ```
   vendor/bin/rector process path/to/file/or/directory
   ```

## Configuration

The Rector configuration is stored in `rector.php`. You can modify this file to change the rules applied to your project.

## Integrating with Your Workflow

- Consider running Rector before committing your code or as part of your CI/CD pipeline.
- You can add a pre-commit hook to automatically run Rector on changed files.

For more detailed information, visit the [Rector documentation](https://getrector.org/).
