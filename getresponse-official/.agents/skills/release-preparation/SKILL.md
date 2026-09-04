---
name: release-preparation
description: Use when preparing a new release of this GetResponse Official WordPress plugin, including version selection, changelog updates, release metadata synchronization, or pre-release validation.
compatibility: Requires git, rg, and the project Docker container wordpress-plugin-php74 for PHPUnit and PHPCS.
---

# Release Preparation

Prepare a release candidate from the current checkout and leave it ready for a separately authorized release action. Evidence from the current branch is more reliable than the highest version visible in another branch or an existing ZIP archive.

## Scope and safety

- Read `AGENTS.md` and `README_TECH.md` before acting.
- Inspect `git status --short --branch` first. Treat existing modifications and untracked files as user-owned.
- If an existing modification overlaps a release file, inspect it and ask before overwriting it.
- Edit only the release files required by the evidence. Do not tidy unrelated files.
- Preparing metadata does not authorize committing, tagging, pushing, building or publishing ZIPs, changing Kubernetes manifests, SVN deployment, or any other deployment action. Report those as remaining actions.
- If the user asks only to analyze or suggest a changelog, stay read-only.

## 1. Establish release evidence

Use the current checkout, not all refs, to determine the release base:

```bash
git status --short --branch
git log --oneline --decorate --no-merges <latest-reachable-stable-tag>..HEAD
git diff --stat <latest-reachable-stable-tag>..HEAD
git diff <latest-reachable-stable-tag>..HEAD --
```

Find the latest stable tag reachable from `HEAD` (for example, with `git describe` and tag inspection). Do not choose a higher version from an unrelated branch, a remote-only ref, or a ZIP filename. If no stable tag is reachable, use the current plugin metadata and explain the uncertainty.

Search the repository for version and release surfaces while excluding generated/dependency content:

```bash
rg -n --hidden -g '!vendor/**' -g '!node_modules/**' -g '!*.zip' \
  '(GETRESPONSE_FOR_WP_VERSION|^ \\* Version:|^Stable tag:|Changelog|version [0-9])' .
```

Confirm that the plugin header and runtime constant agree before selecting a new version. The `tests/bootstrap.php` version is a test fixture and is not a release version.

## 2. Select the version and draft the entry

Classify the user-visible changes since the reachable stable tag using SemVer:

- Bug fixes, corrections, compatibility fixes, and internal reliability improvements → patch increment.
- Backward-compatible user-facing features or integrations → minor increment.
- Breaking behavior or compatibility changes → major increment.

Use the smallest version supported by the change set. If the classification or release base is ambiguous, present the evidence, proposed version, date, and changelog before editing and ask for confirmation.

Write one concise, user-facing entry. Mention the outcome and affected integration, not test files, implementation details, or ticket identifiers unless the project convention requires them. Reuse the same wording in every changelog surface, changing only the surrounding syntax.

Example:

```text
Fixed live synchronization of WooCommerce customers for the ContactsOnly integration
```

## 3. Synchronize release metadata

For a normal release, update these four files and no others:

| File | Required fields | Format |
| --- | --- | --- |
| `getresponse-for-wp.php` | Plugin header `Version:` and `GETRESPONSE_FOR_WP_VERSION` | `1.2.3` |
| `README.txt` | `Stable tag:` and top changelog entry | `= 1.2.3 =` |
| `CHANGELOG.md` | Top changelog entry | `#### 1.2.3 - YYYY-MM-DD` |
| `changelog.txt` | Top marketplace changelog entry | `YYYY-MM-DD - version 1.2.3` |

Put the new entry above the previous release and preserve the existing history. Keep the release date consistent across all three changelogs. Do not change dynamic consumers of the runtime constant, test fixtures, dependency versions, or existing ZIP archives.

## 4. Validate the candidate

Run these checks after editing:

```bash
git diff --check
git diff --name-only
```

Verify that all release version fields equal the selected version and that the new entry appears at the top of all three changelogs. Confirm the final diff contains only intended release files, accounting for any files that were already modified before this task.

Project quality commands must run inside Docker, never on the host:

```bash
docker exec -it wordpress-plugin-php74 vendor/bin/phpunit -c tests/phpunit.xml
docker exec -it wordpress-plugin-php74 vendor/bin/phpcs --standard=phpcs.xml
```

If the container exists but is stopped, inspect it with `docker ps -a --filter name=wordpress-plugin-php74` and start that existing local container before retrying. If Docker access, the container, or dependencies remain unavailable, report the exact blocker; do not claim tests passed and do not work around the project’s Docker requirement.

## Handoff report

Report the result in this order:

1. Selected version and release date.
2. Change evidence and SemVer rationale.
3. Changelog entry.
4. Files changed.
5. Validation commands and outcomes.
6. Explicitly state that commit, tag, push, artifact publishing, and deployment were not performed unless separately authorized.

## Common mistakes

| Mistake | Correction |
| --- | --- |
| Selecting the highest version visible in `git log --all` | Use the latest stable tag reachable from the current `HEAD`. |
| Updating the header but not the runtime constant | Treat both fields as one version pair and verify them together. |
| Forgetting `README.txt`'s `Stable tag:` | Include it in the release-file matrix and consistency check. |
| Updating only one changelog representation | Update `README.txt`, `CHANGELOG.md`, and `changelog.txt` together. |
| Rebuilding or replacing existing ZIPs automatically | Leave artifacts untouched unless the user explicitly requests a build. |
| Treating a stopped Docker container as a test pass | Start the existing container or report validation as blocked. |
| Committing because the diff is ready | Stop at the release candidate; committing and deployment are separate authorized actions. |
