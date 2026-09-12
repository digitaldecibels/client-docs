---
name: project-analysis
description: Detect a project's real technology stack from the repository, within a scan budget, without reading vendored code. Covers stack detection signals, the ignore rules, Drupal-specific inspection, and the configuration truth problem where meaningful state lives in a database rather than in version control. Load before /docs-init or any deep audit.
---

# Project analysis

Work out what the project actually is, from the repository, cheaply.

Load `documentation-rules` alongside this. The evidence hierarchy there governs
what you may conclude from what you find.

## Stay inside the scan budget

A real Drupal or Node repository is mostly code nobody on this project wrote.
Reading it is slow and tells you nothing.

**Never read the contents of:**

```
vendor/                          node_modules/
web/core/    core/               web/modules/contrib/   modules/contrib/
web/themes/contrib/              themes/contrib/
dist/  build/  .next/  out/      .git/
files/  sites/*/files/           images, fonts, binaries
```

Lock files are opened only to read dependency versions, never scanned whole.

**Respect `.gitignore`.** A file git ignores is not evidence about the project.
It may be one developer's local experiment.

Contributed and core code is identified **by name and version from the
dependency manifest**, never by reading its source. You do not need to open
`web/modules/contrib/metatag` to know the site uses Metatag 2.1.

### Reading order

1. Dependency manifests and configuration at the repository root
2. Custom code: custom modules, custom themes, `app/`, `src/`
3. Infrastructure and CI configuration
4. Tests
5. Everything else

### Caps

Set a practical cap before you start, around 200 files read and 6 directories
deep, and stop when you hit it. **Say so in the summary.** A partial analysis
presented as complete is the failure mode here.

```
Reached the file cap while scanning web/modules/custom. 3 modules were
analysed; be_the_ray_migrate was not. Re-run with a narrower scope if it
matters.
```

## Detection

Never depend on a single file. Each technology below has several signals; two
weak signals beat one strong one, and one signal alone is a hypothesis.

| Technology | Signals |
| --- | --- |
| PHP | `composer.json`, `composer.lock`, `*.php` |
| Drupal | `composer.json` requiring `drupal/core*`, `core.extension.yml`, `*.info.yml`, `*.routing.yml`, `*.services.yml`, `web/sites/default/settings.php` |
| Laravel | `artisan`, `laravel/framework` in composer, `app/Http/Kernel.php` |
| Node | `package.json`, any lock file |
| React | `react` dependency, `.jsx`/`.tsx` |
| Next.js | `next.config.*`, `next` dependency, `app/` or `pages/` |
| Vue | `vue` dependency, `.vue` files, `vite.config` with the Vue plugin |
| Tailwind | `tailwind.config.*`, a `tailwindcss` dependency, `@tailwind` or `@import "tailwindcss"` in CSS |
| Alpine | `alpinejs` dependency, `x-data` in templates |
| Vite | `vite.config.*`, `vite` dependency |
| Docker | `Dockerfile`, `docker-compose.yml`, `compose.yml` |
| Lando | `.lando.yml` |
| DDEV | `.ddev/config.yaml` |
| Pantheon | `pantheon.yml`, `pantheon.upstream.yml`, a `pantheon-systems/*` composer package |
| GitHub | `.github/` |
| CI/CD | `.github/workflows/*`, `.gitlab-ci.yml`, `bitbucket-pipelines.yml`, `.circleci/` |
| AWS | `aws-sdk` dependencies, `serverless.yml`, Terraform with an AWS provider |

**A config file that exists is not proof the thing is in use.** A `.ddev/`
directory alongside a `.lando.yml` that every script references means DDEV is
leftover. Check which one the project's own commands and documentation use, and
say which is live.

### What to establish

Languages, frameworks, CMS, frontend stack, build tools, package manager,
database, local environment, hosting and deployment, external services, APIs,
authentication, scheduled jobs, queues, storage, testing, CI/CD, Docker,
environment variables, and the configuration files that matter.

For environment variables: collect **names and purposes** from `.env.example`,
`docker-compose.yml`, CI configuration and code that reads them. Never values.

## The configuration truth problem

**This is the single most important correctness rule, and it is not only a
Drupal problem.**

Much of what defines a Drupal site lives in the database, not the repository:
enabled modules, content types, fields, vocabularies, views, permissions,
roles, blocks, menus. The repository knows about these only if configuration
has been exported.

**An exported configuration directory is a snapshot, not live truth.** It can
be stale, partial, or absent.

Before documenting anything derived from it:

1. **Find the configuration directory.** Check `settings.php` for
   `$settings['config_sync_directory']` and `composer.json` for a configured
   path. Do not assume `config/sync`; this project uses `config/`.

2. **If there is no exported configuration, do not document the content model
   at all.** Not content types, not views, not fields, not permissions, not
   enabled modules. Mark the whole content model REQUIRES CONFIRMATION and say
   plainly that it cannot be determined from the repository.

3. **If it exists, assess whether it looks current:**
   - Compare modules in `core.extension.yml` against what `composer.json` and
     `composer.lock` actually provide. A module enabled in config but absent
     from composer is drift.
   - Compare the last commit date of the configuration directory against the
     last commit date of custom code.
   - Look for configuration referencing modules that are no longer required.

4. **Report the assessment honestly:**

   > Configuration was last exported 8 months ago while custom code changed
   > last week. Content model details are likely stale.

5. **When it looks stale, label everything derived from it as inferred**, not
   verified, and recommend a fresh export before generating a client handoff.

The same treatment applies anywhere meaningful state lives outside version
control: CMS settings, hosting dashboards, DNS, third-party service
configuration. Document what the repository proves and flag the rest.

## Drupal specifics

When Drupal is detected, and only from exported configuration and custom code:

| Look at | For |
| --- | --- |
| `composer.json`, `composer.lock` | Drupal version, contributed modules and versions, patches |
| `core.extension.yml` | Which modules are actually enabled |
| `node.type.*.yml`, `field.field.node.*` | Content types and their fields |
| `taxonomy.vocabulary.*.yml` | Vocabularies |
| `views.view.*.yml` | Views, their displays and paths |
| `user.role.*.yml` | Roles and permissions |
| `paragraphs.paragraphs_type.*.yml` | Paragraph types, if Paragraphs is used |
| `web/modules/custom/*` | Custom modules: routes, services, hooks, Drush commands, queues, cron |
| `web/themes/custom/*`, or a custom theme outside `contrib` | Templates, libraries, preprocess functions, single directory components |
| `*.info.yml` | What each custom module and theme claims to be |
| `pantheon.yml`, `.lando.yml` | Deployment and local environment |

For a client handoff, identify the **administrative workflows** that matter:
how content gets published, what an editor can change, what needs a developer.

**Do not dump every Drupal file into the handoff.** Nobody needs a list of 140
config entities. They need to know there are two content types and what each is
for.

## Recording what you found

Write it to the manifest at `docs/.client-docs.yml`, which is both the record
and the plugin's activation flag. See `documentation-maintenance` for the
schema.

Record for every finding **what proved it**. The manifest's job is making the
next run faster and more deterministic, and evidence is what allows a later run
to re-check one claim instead of redoing the whole analysis.
