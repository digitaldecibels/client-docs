# Documentation rules for this project

Every client-docs command reads this file before it writes anything, and a rule
here wins over the plugin's own rules where the two disagree.

Write rules as plain sentences, one idea each. Say what to do and, where it is
not obvious, why. For example:

- The client calls the site "the Micro-Site". Use that name, never "the app".
- DDEV is the primary local environment. Lando is kept for one developer.
- Do not document the campaign tracker's CSV format here. It lives in
  `web/themes/example/COMPONENTS.md`.
- INTEGRATIONS.md is read by the client, so write it without developer jargon.

Structured choices (which documents exist, tab order, sidebar order, the Open
questions page, colours) belong in `client-docs.config.yml`, not here.

What a rule here cannot change: the plugin never invents a fact the repository
does not show, never writes a secret value, and never overwrites text outside
its generated markers.

## Rules
