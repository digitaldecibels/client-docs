---
name: client-handoff
description: Produce a client-facing handoff document and a shareable site from it. Section selection, evidence tracking in the manifest, the VERIFIED / CLIENT ACTION REQUIRED / REQUIRES CONFIRMATION split, secret handling, and how repeat handoffs report only what changed. Load before /docs-handoff.
---

# Client handoff

Write `docs/CLIENT-HANDOFF.md` for someone who did not build the application: a
client, a project manager, or the developer who inherits it.

Load `documentation-rules` alongside this. Nothing matters more here than not
inventing things, because this is the document people act on.

## Who is reading

Assume they are competent and busy, and that they do not know the stack. They
want to know what they now own, what they have to do, and who to call when it
breaks.

- Avoid jargon where a plain word exists. Say "the site's content is edited in
  Drupal's admin interface", not "content entities are managed via the CMS
  backend".
- Explain anything that changes what they must do.
- Never expose secrets, internal reasoning, or unsupported claims.
- Keep it navigable. Headings and tables, not walls of prose.

## Sections

Include only what applies. A React site with no CMS does not get a Content
Management section, and padding it out with "not applicable" wastes the
reader's attention.

The full menu:

1. Project Overview
2. Technology Stack
3. Installed Modules or Packages
4. Site or Application Architecture
5. Content Components
6. Important URLs
7. User Roles
8. Content Management
9. Common Administrative Tasks
10. Local Development
11. Deployment
12. Hosting
13. Domains and DNS
14. Third-Party Services
15. API Integrations
16. Environment Configuration
17. Scheduled Jobs
18. Backups
19. Monitoring
20. Security
21. Maintenance
22. Troubleshooting
23. Known Limitations
24. Known Issues
25. Future Recommendations
26. Emergency and Recovery Procedures

Several of these usually cannot be answered from a repository. Domains, DNS,
backups, monitoring and emergency procedures typically live in a hosting
dashboard or somebody's head. That is expected. Mark them and move on.

## Installed modules or packages

An inventory section, and the only one in the document that is allowed to be
long. It answers a question clients and inheriting developers both ask: what is
this thing made of, and what is each piece for.

**One line each, in plain language, saying what it does for this site.** Not
what the package does in general. "Linkit" is not an answer; "lets an editor
search for a page by title instead of pasting a URL" is.

Get the list from the dependency manifest and the enabled list, never by
reading the module's source:

| Framework | List from | Enabled state from |
| --- | --- | --- |
| Drupal | `composer.json` | `core.extension.yml` |
| Node | `package.json` `dependencies` | not applicable |
| Laravel | `composer.json` | `config/app.php` providers |

For the one-line description, the package's own manifest is the cheapest honest
source: a Drupal module's `<name>.info.yml` `description`, or the `description`
in a Node package's `package.json`. **This is a deliberate exception to the
scan budget in `project-analysis`**, which otherwise forbids opening anything
under `contrib/` or `node_modules/`. Read that one key from that one file.
Nothing else, and never the module's PHP or JavaScript.

Those descriptions are written for developers, so rewrite each one for the
reader. If a module's own description does not survive rewriting into something
a client would understand, say what it does here instead, and only from
evidence.

Keeping it readable:

- **Group by what the client would recognise**, not alphabetically. Suggested
  groups: editing, media, search engine optimisation, security, performance,
  development only.
- **Say which ones are development only and not enabled on the live site.**
  This matters: a client reading `devel` in a list will ask about it. Prove it
  from the enabled list rather than assuming.
- **Drupal core modules are not worth listing individually.** One sentence
  saying core supplies the basics covers it. List contributed and custom.
- **Custom modules get more than one line**, because nobody else can look them
  up. Say what it does and what breaks without it.
- **Note any module carrying a patch**, from the `patches` block in
  `composer.json`, because a patch is a maintenance obligation the client is
  taking on.

If the list runs past about forty rows, keep the groups and say plainly at the
top how many there are, rather than trimming silently.

## Content components

For a site built from components (Drupal paragraph types, a block library, a
component library), list each component and the fields an editor fills in.

This is the closest thing to a manual for the people who will use the site
daily, and it is the section most likely to be read more than once.

Per component:

| What | From |
| --- | --- |
| Its name as an editor sees it | the type's label |
| What it is for, one line | the type's description |
| Each field, its label, and whether it is required | the field config |
| What kind of thing each field takes | the field type, in plain words |

Say the field type the way an editor experiences it. "Entity reference to
media" is the machine's name for it; "an image chosen from the media library"
is what they see.

Where to read it, from exported configuration rather than the database:

| Drupal | File |
| --- | --- |
| Component name and description | `paragraphs.paragraphs_type.*.yml` |
| Which fields it has, and their labels and required flags | `field.field.paragraph.<type>.*.yml` |
| What each field stores | `field.storage.paragraph.*.yml` |
| The order an editor sees them in | `core.entity_form_display.paragraph.<type>.default.yml` |

**Field order is the form display order, not the order the field files appear
in.** Read the form display's `content` block and sort by its `weight`, or the
list will not match what an editor sees, which makes it worse than no list.

Two things to leave out: fields hidden on the form display, since an editor
never meets them, and the machine names, unless the project's own conventions
make them useful to an inheriting developer.

If the project generates this structure from a script rather than by hand, that
script is better evidence than the exported configuration, because it usually
carries the descriptions too. Record it as the evidence in the manifest.

## Three kinds of statement

Separate them clearly. This is the structural point of the document.

**VERIFIED** means supported by repository evidence. Say what the thing is.

**CLIENT ACTION REQUIRED** means something the client must provide, confirm or
configure. This is the section they will actually read, so it goes near the
top and each item says what is needed and why.

**REQUIRES CONFIRMATION** means it cannot be determined from the repository.
Say what is unknown and what would answer it, so it is a question someone can
act on rather than a gap.

## Secrets

Existence and purpose, never the value:

```
SENDGRID_API_KEY
Purpose: transactional email
Value:   configured outside the repository
```

This holds even when the value is readable in an ignored file. Handoff
documents get emailed.

## Evidence tracking

For every section, record in the manifest which files support it:

```yaml
handoff_evidence:
  deployment:
    claim: Deployed to Pantheon via a Quicksilver hook that imports config
    evidence:
      - pantheon.yml
    verified_commit: a1b2c3d
```

**Keep this in the manifest only, never in the client-facing document.** The
client does not need file paths; the next audit does.

It is what makes later runs fast: re-check a section against its evidence files
rather than re-analysing the repository.

## Repeat handoffs

After the first one, **do not silently rewrite the document.** A retainer
client cares about what changed, not about re-reading forty sections.

1. Compare the implementation against `handoff_evidence` and each
   `verified_commit`.
2. Update what needs updating.
3. Write a **Changes Since Last Handoff** summary covering only what a client
   or project manager would care about:
   - new integrations
   - changed deployment
   - new administrative workflows
   - removed features
   - new client actions required
4. Record the handoff date and commit in `last_handoff`.
5. Put the summary in its own section at the top, or as a separate dated file
   if the client prefers a clean document. Cap it at one page.
6. **Skip routine dependency bumps and internal refactors.** They are not
   changes to a client.
7. If nothing client-relevant changed, say exactly that:

   > No client-relevant changes since the last handoff on 12 August. Dependency
   > updates and internal refactoring only.

   Do not pad it.

## The deliverable

**The document, and nothing else.** `/docs-handoff` writes
`docs/CLIENT-HANDOFF.md`. It does not build a site unless `--site` is passed,
in which case it also runs the `/docs-site --handoff` behaviour into
`docs/handoff-site/`.

This used to happen on every run, on the reasoning that a handoff gets emailed
and should be ready to send. The cost was worse than the convenience: on any
project that serves its documentation another way, it left a second copy of the
same content in the repository that nothing linked to, nobody rebuilt, and that
drifted out of date the moment the markdown changed. A stale duplicate is worse
than a missing convenience.

So the default summary names one artefact:

```
docs/CLIENT-HANDOFF.md updated.

Changes since last handoff: 2 (new Mailgun integration, deployment now
imports config automatically)
3 items need confirmation before this goes to the client. Run /docs-confirm.
```

Add the site's location only when `--site` was passed and the build ran.

## The document metadata

Keep it unobtrusive. An HTML comment at the very top, nothing visible:

```
<!-- Generated by client-docs. Last verified: 2026-09-09 -->
```

A client-facing document covered in provenance headers looks machine-made,
which undermines the thing you are handing over.
