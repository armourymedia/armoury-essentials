# Armoury Essentials

Custom WordPress plugin installed on every site Armoury Media hosts. This repository is public.

## Deploy model

- Sites self-update through the vendored plugin-update-checker: latest GitHub release, then latest tag, then the `main` branch head.
- Publishing a release or tag deploys to every hosted site within the next update check. Bump `Version` in the plugin header and `AE_VERSION` together; a version bump on `main` alone is enough to trigger the rollout.
- Test on one low-risk site before any release. Do not push a version bump without Greg's go-ahead.
- The Armoury child theme styles the plugin-owned class `.ae-video-wrapper`. Renaming or removing plugin CSS classes has no remote fix on built sites.

## Where the fleet facts live

- Server, PHP, WordPress, and per-site plugin roster: `~/projects/armoury-media/CLAUDE.md` and `~/projects/armoury-media/docs/operations/hosting-stack.md`.
- SSH and WP-CLI method for read-only checks: the server-access memory in the armoury-media project store.
- Live state beats the docs. When a review depends on a site fact, read it with WP-CLI rather than from the roster.

## Review policy

- Findings before edits. Report ranked findings with file and line; Greg approves items individually.
- Skip cosmetic and coding-standard findings unless they hide a bug.
- Never print credential constants from a site. Report `defined()` only.
- The `plugin-update-checker` directory is third-party. Report upstream version drift; do not update it inside an unrelated change.

## Local tooling

- No PHP on the workstation. Static review only; execution happens on the server through read-only WP-CLI.
