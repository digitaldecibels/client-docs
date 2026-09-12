---
name: seo-buildout
description: Build out the SEO layer for a Drupal site - metatag defaults, Open Graph, Twitter Cards, Facebook tags, Schema.org JSON-LD, the XML sitemap, robots.txt and llms.txt. Use when asked to set up SEO, meta tags, social sharing, structured data, schema, rich results, sitemaps, or to make a site's SEO solid. Enumerates content types with Drush rather than assuming them, so it covers whatever the site actually has.
---

# SEO buildout for a Drupal site

Work through this in order. Every step ends with something you can check in the
page source, so nothing is taken on trust.

`drush` throughout means whatever runs Drush on this project: `lando drush`,
`ddev drush`, `terminus drush <site>.<env> --`, or plain `drush` where it is on
the path. Check which before running the first command rather than guessing.

## 0. Find out what the site actually has

Never assume the content types. Ask:

```bash
drush php:eval 'foreach (\Drupal::entityTypeManager()->getStorage("node_type")->loadMultiple() as $t) { printf("%-20s %s\n", $t->id(), $t->label()); }'
drush php:eval 'foreach (\Drupal::entityTypeManager()->getStorage("media_type")->loadMultiple() as $t) { printf("%-20s %s\n", $t->id(), $t->getSource()->getPluginId()); }'
drush php:eval 'foreach (\Drupal::entityTypeManager()->getStorage("taxonomy_vocabulary")->loadMultiple() as $v) { print $v->id() . "\n"; }'
drush pm:list --status=enabled --format=list | grep -iE "metatag|schema|sitemap|redirect|pathauto|token"
```

Then decide, per content type, which Schema.org type fits. An editorial piece
is an `Article`. A general page is a `WebPage`. A person, an event, a course
and a job posting each have their own, and `schema_metatag` ships a submodule
per type. Guessing `WebPage` for everything is a wasted opportunity, and
guessing `Article` for a contact page is wrong.

## 1. Modules

```bash
drush en metatag metatag_open_graph metatag_twitter_cards metatag_facebook -y
drush en schema_metatag schema_web_site schema_organization schema_article schema_web_page -y
drush en simple_sitemap -y
```

Add a `schema_*` submodule for every Schema.org type the site needs; they are
per-type by design.

**Only one sitemap module.** `simple_sitemap` and `xmlsitemap` both claim
`/sitemap.xml` and will fight. simple_sitemap is the current one for Drupal 11.
If both are in composer.json, install one and say the other should come out.

## 2. Understand what you are writing, or you will write it four times badly

These vocabularies overlap and each is read by different software. The same
headline genuinely does belong in three or four places; that is not duplication
to tidy away.

| Vocabulary | Read by | Prefix |
| --- | --- | --- |
| Open Graph | Facebook, LinkedIn, Slack, WhatsApp, most link previews | `og:` |
| Twitter Cards | X, falling back to Open Graph for anything missing | `twitter:` |
| Facebook | Nothing to do with sharing. Ties the page to a Facebook app or page for analytics | `fb:` |
| Schema.org | Search engines, for rich results | JSON-LD |

Leave the `fb:` tags empty unless somebody supplies an app or page id. They do
nothing on their own.

## 3. Fields, so editors can override

Defaults are not enough on their own. Add to every content type:

- `field_meta_tags`, type `metatag`, widget `metatag_firehose`. Without it there
  is no way to write a different description for one page.
- `field_share_image`, an image media reference. Social previews want roughly
  1200x630, and the page's own hero is often the wrong shape or missing.

Hide both from the view display. They exist for the `<head>`.

## 4. Defaults

Write `metatag.metatag_defaults.*`: `global`, `front`, and one per content type
as `node__<type>`.

Put **WebSite and Organization on the front page only**. On every page, a search
engine sees the same organisation declared a few hundred times.

### Tokens, and the two traps

Use `token_or`'s pipe syntax for fallbacks, first non-empty wins:

```
[node:field_share_image:entity:field_media_image:og_image:url|node:field_image:entity:field_media_image:og_image:url|"/themes/x/images/share-default.png"]
```

**Never nest a token inside the quoted fallback.** token_or finds candidates
with a regex that cannot match a bracket group containing another bracket, so
`|"[site:url]share.png"` makes the whole thing fail and prints the raw
`[a|b|c]` into the page. Use a relative path; Metatag makes URL tags absolute.

**Ask for an image style derivative, not the original.** A token like
`[media:field_media_image:og_image:url]` returns a cropped 1200x630 file. The
original can be several megabytes, and some platforms simply refuse it.

Verify the tokens resolve before believing them:

```bash
drush php:eval '$n = \Drupal::entityTypeManager()->getStorage("node")->load(1); print \Drupal::token()->replace("[node:field_image:entity:field_media_image:og_image:url]", ["node" => $n], ["clear" => TRUE]) . "\n";'
```

### Schema.org values are not strings

Two things catch people:

- **Tag ids are snake_case**, and not every property exists. `schema_article_date_published`, not `datePublished`. `schema_web_page` has no name, url or image tag at all. Ask the plugin manager rather than guessing:

  ```bash
  drush php:eval '$m = \Drupal::service("plugin.manager.metatag.tag"); $ids = array_filter(array_keys($m->getDefinitions()), fn($i) => str_starts_with($i, "schema_article")); sort($ids); print implode("\n", $ids) . "\n";'
  ```

- **Nested values are serialised arrays.** An image is an ImageObject, not a URL; an author is an Organization or Person. Pass a bare URL and the module drops the property silently.

  ```php
  'schema_article_image' => serialize([
    '@type' => 'ImageObject',
    'url' => $image_token,
    'width' => '1200',
    'height' => '630',
  ]),
  ```

## 5. A default share image that actually renders

Facebook and X do not render SVG. If the only fallback is a logo SVG, every
page without its own image shares as a blank box.

Generate a 1200x630 PNG and a square logo PNG. If there is no design for one,
compose it from the site's logo on the brand colour: an HTML page rendered in a
browser and screenshotted is a perfectly good way to do it.

## 6. Sitemap

Index **every** content type, found by asking Drupal, not by listing them:

```php
$bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo('node');
foreach (array_keys($bundles) as $bundle) {
  $manager->setBundleSettings('node', $bundle, ['index' => TRUE, 'priority' => '0.5', 'changefreq' => 'monthly']);
}
```

Exclude users, media and usually taxonomy: they are not pages anyone should
land on from a search result. State the exclusion rather than leaving it to the
default, so the intent is visible later.

Add a `hook_ENTITY_TYPE_insert()` for `node_type` that indexes any content type
created later. Otherwise a type added in six months is silently missing, and
nobody finds out for a year.

Turn on `cron_generate`. The sitemap is a cached artefact, not a live query.

```bash
drush simple-sitemap:generate
curl -s https://site/sitemap.xml | grep -c "<url>"
```

## 7. llms.txt

A newer convention, at `/llms.txt`, telling large language models what the site
is and which pages matter. Cheap to add and increasingly read.

```bash
composer require drupal/llms_txt
drush en llms_txt -y
```

If that module is not available for the site's Drupal version, the file is
plain Markdown and can be served from the docroot or a tiny route. It is not
worth blocking on.

Generate a default and then **edit it**, because the auto-generated version is
a link dump:

```markdown
# Site Name

> One sentence on what this site is and who it is for.

Two or three sentences of real context. What the organisation does, what a
reader will find here, anything that stops a model guessing wrong.

## Key pages

- [About](https://site/about): what the campaign is and why
- [Give](https://site/give): how to donate
- [News](https://site/news): stories, newest first

## Notes

- Canonical domain is https://site
- Content is published by <organisation>
```

## 8. robots.txt

On Pantheon and most scaffolded installs this is a real file under version
control, not something a module should rewrite. Check it allows crawling and
points at the sitemap:

```
Sitemap: https://site/sitemap.xml
```

Do not let a module edit it behind your back. Edit the scaffolded file.

## 9. Improvements worth offering

Raise these rather than assuming them, and say what each buys:

- **Canonical URLs and redirects.** `redirect` plus `pathauto` means an edited title does not orphan the old URL. Check `redirect` is enabled and that it auto-creates on alias change.
- **`hreflang`** if the site is ever multilingual. Cheap now, awkward later.
- **BreadcrumbList schema** on interior pages, which is what produces the breadcrumb trail in a search result rather than a bare URL.
- **`FAQPage` or `HowTo`** where the content genuinely is one. Do not fake it; Google penalises it.
- **404 and 410 handling.** A soft 404 returning 200 is worse than a real 404.
- **Image alt text as a required field**, which is SEO and accessibility at once.
- **Page titles that are not just `[node:title] | [site:name]`** on high-value pages.
- **`noindex` on listing pages with filters applied**, so a hundred filter permutations do not become a hundred thin pages. Worth discussing rather than assuming.

## 10. Verify, do not assume

```bash
drush cr
curl -s "https://site/some-node" | grep -oE '<meta (property|name)="(og:|twitter:|description)[^>]*>'
curl -s "https://site/some-node" | python3 -c "import sys,re,json; [print(json.dumps(json.loads(m), indent=1)) for m in re.findall(r'<script type=\"application/ld\+json\">(.*?)</script>', sys.stdin.read(), re.S)]"
```

Check a real node of **every** content type, not just one. Check the front page
separately, because it carries WebSite and Organization and nothing else does.

Then run Lighthouse and expect SEO at 100. If `link-text` fails on wording the
design mandates, say so rather than changing the design.

Finally, export and check nothing unintended came with it:

```bash
drush cex -y
git diff config/core.extension.yml
```
