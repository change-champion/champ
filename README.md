<p align="center">
<img src="art/logo.svg" alt="Change Champion" width="250">
</p>

<p align="center">
A tool to manage versioning and changelogs for Composer packages, inspired by <a href="https://github.com/changesets/changesets">changesets</a>.
</p>

<p align="center">
    <a href="https://github.com/change-champion/champ/actions"><img src="https://img.shields.io/github/actions/workflow/status/change-champion/champ/tests.yml?branch=main&style=flat-square&label=tests" alt="Test Status"></a>
    <a href="https://packagist.org/packages/change-champion/champ"><img src="https://img.shields.io/packagist/dt/change-champion/champ.svg?style=flat-square" alt="Packagist Downloads"></a>
    <a href="https://packagist.org/packages/change-champion/champ"><img src="https://img.shields.io/packagist/v/change-champion/champ.svg?style=flat-square" alt="Packagist Version"></a>
    <a href="https://packagist.org/packages/change-champion/champ"><img src="https://img.shields.io/github/license/change-champion/champ.svg?style=flat-square" alt="License"></a>
</p>

## Installation

```bash
composer require change-champion/champ --dev
```

Or install globally:

```bash
composer global require change-champion/champ
```

## Quick Start

```bash
# Initialize in your project
champ init

# Create a changeset when you make changes
champ add

# View pending changesets
champ status

# Apply version bump and generate changelog
champ version

# Create a git tag
champ publish
```

## Commands

### `champ init`

Initialize change-champion in your project. Creates a `.changes` directory with configuration.

```bash
champ init

# With GitHub Actions automation
champ init --with-github-actions
```

**Options:**

- `--with-github-actions` - Also install GitHub Actions workflows for automation

### `champ add`

Create a new changeset. Run this after making changes that should be released.

```bash
# Interactive mode
champ add

# Non-interactive mode
champ add --type=minor --message="Add user authentication feature"

# Create empty changeset (useful for CI)
champ add --empty
```

**Options:**

- `--type`, `-t` - Bump type: `major`, `minor`, or `patch`
- `--message`, `-m` - Summary of the change
- `--empty` - Create an empty changeset

### `champ generate`

Generate changesets from conventional commits (hybrid mode like release-please).

```bash
# Generate from latest tag to HEAD
champ generate

# Preview without creating files
champ generate --dry-run

# Specify commit range
champ generate --from=v1.0.0 --to=HEAD
```

**Options:**

- `--from` - Starting ref (tag, commit, or branch). Defaults to latest tag.
- `--to` - Ending ref. Defaults to HEAD.
- `--dry-run` - Show what would be generated without creating files

**Conventional commit types:**

| Commit Type | Changeset Type | Example                             |
|-------------|----------------|-------------------------------------|
| `feat`      | minor          | `feat: add user authentication`     |
| `feat!`     | major          | `feat!: remove deprecated API`      |
| `fix`       | patch          | `fix: resolve null pointer`         |
| `perf`      | patch          | `perf: optimize database queries`   |
| `refactor`  | patch          | `refactor: extract helper function` |
| `docs`      | ignored        | `docs: update README`               |
| `chore`     | ignored        | `chore: update dependencies`        |
| `test`      | ignored        | `test: add unit tests`              |
| `ci`        | ignored        | `ci: update workflow`               |

**Breaking changes** are detected via `!` suffix (e.g., `feat!:`) or `BREAKING CHANGE:` in the commit body.

### `champ status`

Show pending changesets and the calculated next version.

```bash
champ status

# Verbose output
champ status -v
```

### `champ version`

Apply all pending changesets: update `CHANGELOG.md` and delete the changeset files.

```bash
# Interactive confirmation
champ version

# Preview changes without applying
champ version --dry-run

# Skip changelog generation
champ version --no-changelog

# Create a pre-release version
champ version --prerelease alpha
champ version --prerelease beta
champ version --prerelease rc
```

**Options:**

- `--dry-run` - Show what would be done without making changes
- `--no-changelog` - Skip changelog generation
- `--prerelease`, `-p` - Create a pre-release version (alpha, beta, rc)

**Pre-release workflow:**

```bash
# Create first alpha (1.0.0 → 1.1.0-alpha.1)
champ version --prerelease alpha

# Bump alpha (1.1.0-alpha.1 → 1.1.0-alpha.2)
champ version --prerelease alpha

# Move to beta (1.1.0-alpha.2 → 1.1.0-beta.1)
champ version --prerelease beta

# Move to RC (1.1.0-beta.1 → 1.1.0-rc.1)
champ version --prerelease rc

# Graduate to stable (1.1.0-rc.1 → 1.1.0)
champ version
```

### `champ preview`

Preview the CHANGELOG entry that would be generated without making any changes.

```bash
# Preview changelog entry
champ preview

# Preview with pre-release version
champ preview --prerelease alpha
```

**Options:**

- `--prerelease`, `-p` - Preview as a pre-release version (alpha, beta, rc)

### `champ check`

Validate changeset files for correct format. Useful in CI to catch errors early.

```bash
champ check
```

Returns exit code `0` if all changesets are valid, `1` if any are invalid.

### `champ publish`

Create a git tag for the current version.

```bash
# Create and push tag
champ publish

# Create tag without pushing
champ publish --no-push

# Preview without creating tag
champ publish --dry-run
```

**Options:**

- `--dry-run` - Show what would be done without making changes
- `--no-push` - Create tag but don't push to remote

### `champ install-skill`

Install the Change Champion Claude skill file into the current project.

```bash
champ install-skill

# Overwrite existing skill file
champ install-skill --force
```

**Options:**

- `--force`, `-f` - Overwrite an existing skill file

Copies the skill file to `.claude/skills/change-champion/SKILL.md` in your project, giving Claude context about Change
Champion commands and workflows.

## Changeset Format

Changesets are stored in `.changes/` as markdown files:

```markdown
---
type: minor
---

Add user authentication with OAuth2 support.
```

## Configuration

Configuration is stored in `.changes/config.json`:

```json
{
  "baseBranch": "main",
  "changelog": true,
  "repository": "https://github.com/owner/repo",
  "sections": {
    "major": "Breaking Changes",
    "minor": "Features",
    "patch": "Fixes"
  },
  "releaseBranchPrefix": "release/",
  "versionPrefix": "v",
  "draftRelease": false
}
```

**Options:**

- `baseBranch` - The base branch for comparisons (default: `main`)
- `changelog` - Whether to generate changelog entries (default: `true`)
- `repository` - Repository URL for linking issues, PRs, and commits (auto-detected from git remote if not set)
- `sections` - Custom section headers for changelog (defaults shown above)
- `releaseBranchPrefix` - Branch prefix for release PRs created by the action (default: `release/`)
- `versionPrefix` - Prefix for version numbers in changelog headers and git tags (default: `v` for `v1.0.0` format)
- `draftRelease` - Create GitHub releases as drafts for manual publishing; used by the action only (default: `false`)

## Auto-linking

Changelog entries are automatically enriched with links when a `repository` URL is configured (or auto-detected from git
remote).

### Issue references

Issue references in changeset summaries are linked:

```markdown
- Fix authentication bug. Fixes [#123](https://github.com/owner/repo/issues/123)
```

Supported patterns: `#123`, `Fixes #123`, `Closes #123`, `Resolves #123`

### PR numbers and commit hashes

PR numbers and commit hashes are automatically appended to each changelog entry from git history:

```markdown
- Add user authentication ([#45](https://github.com/owner/repo/pull/45)) ([abc1234](https://github.com/owner/repo/commit/abc1234...))
```

PR numbers are extracted from squash merge subjects (`feat: some change (#45)`) and merge commits (
`Merge pull request #45 from ...`). If a PR number already appears in the changeset summary, it won't be duplicated.

## Semantic Versioning

Change Champion follows [semver](https://semver.org/):

- **patch** (0.0.x) - Bug fixes, minor changes
- **minor** (0.x.0) - New features, backwards compatible
- **major** (x.0.0) - Breaking changes

When multiple changesets exist, the highest bump type wins. For example, if you have one `minor` and one `patch`
changeset, the version will be bumped as `minor`.

## Workflow

1. Make changes to your code
2. Run `champ add` to create a changeset describing your changes
3. Commit the changeset file along with your code changes
4. When ready to release, run `champ version` to bump versions and update changelog
5. Commit the version bump
6. Run `champ publish` to create a git tag
7. Push the tag to trigger your release pipeline

## GitHub Actions

The quickest way to automate releases is with `champ init`:

```bash
champ init --with-github-actions
```

This installs a workflow (`.github/workflows/change-champion.yml`) that uses
the [change-champion/action](https://github.com/change-champion/action) GitHub Action to handle:

- **check** — Comments on PRs that don't include a changeset
- **release** — Creates a release PR when changesets are merged to `main`
- **publish** — Creates a git tag and GitHub Release when a release PR is merged

### Setup

After installing the workflow, enable the required permissions:

1. Go to **Settings → Actions → General**
2. Under **Workflow permissions**, enable:
    - "Read and write permissions"
    - "Allow GitHub Actions to create and approve pull requests"

For full action documentation, see the [change-champion/action](https://github.com/change-champion/action) repository.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
