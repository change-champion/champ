---
name: change-champion
description: Use when creating changesets, checking release status, previewing changelogs, or performing versioning and publishing with Change Champion. Relevant for any task involving version bumps, changelog generation, or release workflows.
argument-hint: [ command ] [ options ]
allowed-tools: Bash(champ *), Bash(./vendor/bin/champ *), Read, Glob, Grep
---

# Change Champion

Change Champion is a PHP/Composer tool for managing semantic versioning and changelogs. It uses changeset files to track
changes, then applies them to bump versions and generate changelogs.

## Commands

### `champ add` — Create a changeset

Creates a `.changes/<id>.md` file describing a change.

**Interactive mode:**

```bash
champ add
```

**Non-interactive:**

```bash
champ add --type=<major|minor|patch> --message="Description of the change"
```

- `--type` / `-t`: The semver bump type (`major`, `minor`, or `patch`)
- `--message` / `-m`: Summary of the change (supports markdown and issue references like `#123`, `Fixes #123`)

### `champ status` — Show pending changesets

```bash
champ status
```

Shows all pending changesets and the calculated next version.

### `champ preview` — Preview changelog entry

```bash
champ preview
```

Shows what the CHANGELOG.md entry would look like without making changes.

### `champ check` — Validate changesets

```bash
champ check
```

Validates that all changeset files have correct format (valid `type` in frontmatter). Useful for CI.

### `champ version` — Apply changesets and bump version

```bash
champ version
champ version --dry-run
champ version --prerelease alpha
```

Consumes all pending changesets, prepends a new entry to `CHANGELOG.md` (unless `--no-changelog`), and deletes the applied changeset files. It does not modify `composer.json`.

- `--dry-run`: Show what would happen without making changes
- `--no-changelog`: Skip changelog generation
- `--prerelease <tag>`: Create a pre-release version (e.g., `alpha`, `beta`, `rc`)

**Pre-release workflow:**

1. `champ version --prerelease alpha` → `1.1.0-alpha.1`
2. `champ version --prerelease alpha` → `1.1.0-alpha.2`
3. `champ version --prerelease beta` → `1.1.0-beta.1`
4. `champ version --prerelease rc` → `1.1.0-rc.1`
5. `champ version` → `1.1.0` (graduate to stable)

### `champ publish` — Create and push git tag

```bash
champ publish
champ publish --dry-run
```

Creates a git tag (e.g., `v1.2.0`) and pushes it to origin.

### `champ generate` — Generate changesets from conventional commits

```bash
champ generate
```

Parses conventional commit messages since the last tag and creates changeset files.

**Conventional commit mapping:**

| Commit prefix                                   | Changeset type |
|-------------------------------------------------|----------------|
| `feat`                                          | minor          |
| `feat!` or `BREAKING CHANGE:`                   | major          |
| `fix`                                           | patch          |
| `perf`                                          | patch          |
| `refactor`                                      | patch          |
| `docs`, `chore`, `test`, `ci`, `style`, `build` | ignored        |

### `champ init` — Initialize in a new project

```bash
champ init
```

Creates the `.changes/` directory and `config.json`.

### `champ install-skill` — Install Claude skill file

```bash
champ install-skill
champ install-skill --force
```

Copies the Change Champion Claude skill file into `.claude/skills/change-champion/SKILL.md` in the current project.

- `--force` / `-f`: Overwrite an existing skill file

## Changeset File Format

Changesets are markdown files in `.changes/` with YAML frontmatter:

```markdown
---
type: minor
---

Add user authentication with OAuth2 support.
```

- **type** (YAML frontmatter): `major`, `minor`, or `patch`
- **markdown body**: Summary text describing the change; supports issue references (`#123`, `Fixes #123`)

## Auto-linking

When a `repository` URL is configured (or auto-detected from git remote), changelog entries are automatically enriched:

- **Issue references**: `#123`, `Fixes #123`, `Closes #123` → linked to `repo/issues/123`
- **PR numbers**: Extracted from git commit subjects (squash merge `(#45)` or merge commit `Merge pull request #45`) → linked to `repo/pull/45`
- **Commit hashes**: Appended as short hash links → linked to `repo/commit/fullhash`

## Configuration

Stored in `.changes/config.json`:

| Key                   | Default              | Description                           |
|-----------------------|----------------------|---------------------------------------|
| `baseBranch`          | `"main"`             | Base branch for comparisons           |
| `changelog`           | `true`               | Whether to generate changelog entries |
| `repository`          | auto-detected        | Repository URL for linking issues, PRs, and commits |
| `sections.major`      | `"Breaking Changes"` | Changelog heading for major changes   |
| `sections.minor`      | `"Features"`         | Changelog heading for minor changes   |
| `sections.patch`      | `"Fixes"`            | Changelog heading for patch changes   |
| `releaseBranchPrefix` | `"release/"`         | Prefix for release branches (used by action) |
| `versionPrefix`       | `"v"`                | Prefix for changelog headers and git tags |
| `draftRelease`        | `false`              | Create GitHub releases as drafts (used by action) |

## Typical Workflow

1. Make code changes on a feature branch
2. Run `champ add --type=minor --message="Add new feature"` to create a changeset
3. Commit the changeset file with your code changes
4. After merging, run `champ version` to bump the version and update CHANGELOG.md
5. Commit the version bump, then run `champ publish` to tag and push

## Guidelines

- Create one changeset per logical change — a PR may have multiple changesets
- Use `major` for breaking changes, `minor` for new features, `patch` for bug fixes
- Write changeset summaries from the user's perspective (what changed, not how)
- Reference related issues with `Fixes #123` or `Closes #123` for auto-linking
- Use `--dry-run` on `champ version` and `champ publish` to preview before applying
- Run `champ status` to see what's pending before versioning
