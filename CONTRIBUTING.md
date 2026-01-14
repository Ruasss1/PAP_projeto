# Contributing to PAP Supermercado (Backend)

Thank you for your interest in contributing! This document outlines the guidelines for contributing to this project.

## Getting Started

1. **Fork** the repository on GitHub.
2. **Clone** your fork locally:
   ```bash
   git clone https://github.com/YOUR_USERNAME/PAP_projeto.git
   cd PAP_projeto
   ```
3. **Add upstream** remote to sync with main repo:
   ```bash
   git remote add upstream https://github.com/Ruasss1/PAP_projeto.git
   ```
4. **Install dependencies**:
   ```bash
   composer install
   ```

## Development Workflow

### Creating a Feature Branch

Use descriptive branch names following this pattern:
- **Features**: `feature/short-description` (e.g., `feature/add-product-validation`)
- **Fixes**: `fix/short-description` (e.g., `fix/order-stock-decrement`)
- **Chores**: `chore/short-description` (e.g., `chore/update-dependencies`)
- **Docs**: `docs/short-description` (e.g., `docs/add-api-guide`)

Example:
```bash
git checkout -b feature/implement-user-authentication
```

### Commits

Write clear, concise commit messages:
```bash
git commit -m "Add user authentication module

- Implement login/logout endpoints
- Add password hashing with bcrypt
- Update README with auth setup instructions"
```

**Guidelines**:
- Start with a short summary (50 chars max)
- Use imperative mood ("Add" not "Added")
- Include a blank line, then a detailed description
- Reference issues: `Fixes #123` or `Relates to #456`

### Running Tests

Before pushing, ensure all tests pass:
```bash
./vendor/bin/phpunit
```

If you add new features, include tests:
```bash
./vendor/bin/phpunit tests/YourFeatureTest.php
```

### Database Migrations

If your change modifies the database schema:
1. Create a migration in `migrations/` (e.g., `002_add_user_auth.sql`)
2. Test locally: `php migrations/migrate.php` (or via browser)
3. Document the migration in the PR description

### Pushing and Creating a Pull Request

1. **Sync with upstream** before pushing:
   ```bash
   git fetch upstream
   git rebase upstream/main
   ```

2. **Push** your branch:
   ```bash
   git push origin feature/your-feature
   ```

3. **Create a PR** on GitHub:
   - Use a descriptive title
   - Reference any related issues: `Fixes #123`
   - Include a clear description of changes
   - Add test evidence (e.g., "Tests: 15 passed")

## Code Style

- **PHP**: Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) standards
- **Indentation**: 4 spaces (not tabs)
- **Naming**: camelCase for functions/variables, snake_case for database columns
- **Comments**: Use clear, concise English comments where logic is non-obvious

Example:
```php
/**
 * Calculates total profit for a date range.
 * 
 * @param string $startDate (Y-m-d format)
 * @param string $endDate (Y-m-d format)
 * @return float Total profit
 */
function calculateProfitForRange($startDate, $endDate) {
    // Implementation...
}
```

## Reporting Issues

If you find a bug or have a feature request:
1. Check existing [Issues](https://github.com/Ruasss1/PAP_projeto/issues)
2. Create a new issue with:
   - Clear title and description
   - Steps to reproduce (for bugs)
   - Expected vs actual behavior
   - Environment (PHP version, OS, database)

## Code Review

All PRs require at least one review before merge. Be open to feedback:
- Respond to comments promptly
- Push additional commits (don't force-push during review)
- Request re-review after making changes

## Security

- **Never commit** credentials, API keys, or sensitive data
- Use `.env` files with `.env.example` templates
- Report security issues privately to the maintainer

## Questions?

Open an issue or discussion on GitHub, or check the main [README](README.md).

Thank you for contributing! 🙏
