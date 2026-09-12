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
3. Site or Application Architecture
4. Important URLs
5. User Roles
6. Content Management
7. Common Administrative Tasks
8. Local Development
9. Deployment
10. Hosting
11. Domains and DNS
12. Third-Party Services
13. API Integrations
14. Environment Configuration
15. Scheduled Jobs
16. Backups
17. Monitoring
18. Security
19. Maintenance
20. Troubleshooting
21. Known Limitations
22. Known Issues
23. Future Recommendations
24. Emergency and Recovery Procedures

Several of these usually cannot be answered from a repository. Domains, DNS,
backups, monitoring and emergency procedures typically live in a hosting
dashboard or somebody's head. That is expected. Mark them and move on.

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

A handoff is only useful if it is easy to hand over.

On every run, also build the client-facing site into `docs/handoff-site/`,
using the `/docs-site --handoff` behaviour. That gives a folder the developer
can zip and email or drop on hosting with no extra step.

Say where it is in the summary:

```
docs/CLIENT-HANDOFF.md updated.
Shareable site: docs/handoff-site/ (open index.html)

Changes since last handoff: 2 (new Mailgun integration, deployment now
imports config automatically)
3 items need confirmation before this goes to the client. Run /docs-confirm.
```

## The document metadata

Keep it unobtrusive. An HTML comment at the very top, nothing visible:

```
<!-- Generated by client-docs. Last verified: 2026-09-09 -->
```

A client-facing document covered in provenance headers looks machine-made,
which undermines the thing you are handing over.
