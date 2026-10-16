#!/usr/bin/env node
// Generates a Starlight site's content pages from a project's docs/*.md.
//
//   node build-starlight.mjs [project root] [--out <dir>]
//
// Reads, in the project:
//   docs/client-docs.config.yml   per-project settings (optional, all keys optional)
//   docs/.client-docs.yml         the manifest, for the Open questions page
//   README.md, docs/*.md          the source pages
//
// Writes docs/starlight/src/content/docs/, which is generated output: every
// .md and .mdx file in it that this run did not produce is deleted, so a page
// removed from the settings disappears from the site. --out writes elsewhere
// instead, which is how to compare a run against the live content.
//
// Also writes docs/starlight/src/sidebar.mjs, the sidebar built from site.order
// and site.groups. astro.config.mjs has to import it for groups to show; when it
// does not, Starlight builds a flat sidebar from each page's sidebar.order, and
// the script says so if groups are set. With --out it lands in that directory.
//
// It does not touch astro.config.mjs, package.json, custom.css or brand.css,
// and it does not run the Astro build. Colour is a judgement step, described in
// commands/docs-site.md, not something this script decides.
//
// No dependencies of its own. js-yaml is resolved from the Starlight project's
// node_modules, where Astro already installs it.

import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';

// ---------------------------------------------------------------------------
// Arguments and paths

const args = process.argv.slice(2);
const outFlag = args.indexOf('--out');
const outArg = outFlag >= 0 ? args.splice(outFlag, 2)[1] : null;
const root = path.resolve(args[0] ?? process.cwd());
const docsDir = path.join(root, 'docs');
const siteDir = path.join(docsDir, 'starlight');
const outDir = path.resolve(outArg ?? path.join(siteDir, 'src/content/docs'));

function fail(message) {
  console.error(`build-starlight: ${message}`);
  process.exit(1);
}

if (!fs.existsSync(path.join(docsDir, '.client-docs.yml'))) {
  fail(`no docs/.client-docs.yml in ${root}. Run /docs-init first.`);
}
if (!fs.existsSync(path.join(siteDir, 'node_modules'))) {
  fail(`no docs/starlight/node_modules. Install the Starlight project first.`);
}

let yaml;
try {
  yaml = createRequire(path.join(siteDir, 'package.json'))('js-yaml');
} catch {
  fail('js-yaml is not resolvable from docs/starlight. It normally arrives with Astro.');
}

const readYaml = (file) =>
  fs.existsSync(file) ? yaml.load(fs.readFileSync(file, 'utf8')) ?? {} : {};

// ---------------------------------------------------------------------------
// Settings: the plugin's defaults, with the project's file merged over them.
// Keep these in step with templates/client-docs.config.yml.

const DEFAULTS = {
  documents: { skip: [] },
  tabs: {
    'local-environment': { enabled: true, order: ['DDEV', 'Lando'], hide: [] },
  },
  site: {
    order: [
      'README', 'INSTALLATION', 'DEVELOPMENT', 'ARCHITECTURE', 'CONTENT-MODEL',
      'MODULES', 'CONFIGURATION', 'DEPLOYMENT', 'INTEGRATIONS', 'CHANGELOG',
    ],
    groups: [],
    exclude: [],
    descriptions: 'first-sentence',
    components: { tabs: true, steps: true, filetree: true },
    open_questions: {
      enabled: true,
      title: 'Open questions',
      position: 'last',
      badge: true,
      icon: 'warning',
    },
  },
  // Audience and customer tagging, for projects that also feed these documents
  // to a chatbot. Off by default: with chat.enabled false, a document's tags are
  // read but never change what is published, so existing projects build exactly
  // as they did before.
  chat: { enabled: false, customers: [] },
  // Not used by this script. Listed so their keys are recognised.
  branding: { primary: null, secondary: null, logo: null, title: null },
};

function merge(base, over) {
  if (over === null || over === undefined) return base;
  if (Array.isArray(base) || typeof base !== 'object' || base === null) return over;
  const out = { ...base };
  for (const [k, v] of Object.entries(over)) out[k] = merge(base[k], v);
  return out;
}

const configFile = path.join(docsDir, 'client-docs.config.yml');
const projectSettings = readYaml(configFile);
const settings = merge(DEFAULTS, projectSettings);

// A misspelt key would otherwise do nothing, silently. Tab groups are open:
// any group name is allowed, with the same three keys as local-environment.
const TAB_KEYS = ['enabled', 'order', 'hide'];
function unknownKeys(over, base, at = '') {
  if (!over || typeof over !== 'object' || Array.isArray(over)) return [];
  return Object.entries(over).flatMap(([k, v]) => {
    const here = at ? `${at}.${k}` : k;
    if (at === 'tabs') {
      return Object.keys(v ?? {}).filter((t) => !TAB_KEYS.includes(t)).map((t) => `${here}.${t}`);
    }
    if (!base || !(k in base)) return [here];
    const b = base[k];
    return b && typeof b === 'object' && !Array.isArray(b) ? unknownKeys(v, b, here) : [];
  });
}
const unknown = unknownKeys(projectSettings, DEFAULTS);
const manifest = readYaml(path.join(docsDir, '.client-docs.yml'));

// ---------------------------------------------------------------------------
// Which pages

// Never published, whatever the settings say. The rules file is the plugin's
// own input and sits in docs/ beside the documents, so it has to be named.
const ALWAYS_EXCLUDED = ['CLIENT-HANDOFF', 'CLIENT-DOCS.RULES'];
const nameOf = (file) => path.basename(file, '.md');
const upper = (list) => (list ?? []).map((n) => String(n).replace(/\.md$/, '').toUpperCase());
const excluded = new Set([
  ...ALWAYS_EXCLUDED,
  ...upper(settings.documents.skip),
  ...upper(settings.site.exclude),
]);

// Problems with a document's tags. Reported at the end, never fatal: a mistyped
// tag should not stop the site building.
const warnings = [];

/**
 * Reads the audience/customer block a document may carry.
 *
 * Flat "key: value" lines between two --- fences, at the very top of the file.
 * Deliberately not a YAML parser: the same three keys are read on the PHP side
 * by the indexer, and two parsers that disagree about an edge case would publish
 * a page the chatbot thinks is private.
 *
 * Returns { meta, body, present }. body always has the block removed, so every
 * offset-anchored match below still sees the document it expects.
 */
function readFrontmatter(text) {
  if (!text.startsWith('---\n') && !text.startsWith('---\r\n')) {
    return { meta: {}, body: text, present: false };
  }
  const normalised = text.replace(/\r\n/g, '\n');
  const end = normalised.indexOf('\n---', 3);
  if (end === -1) return { meta: {}, body: text, present: false };

  const meta = {};
  for (const line of normalised.slice(4, end).split('\n')) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const colon = trimmed.indexOf(':');
    if (colon === -1) continue;
    const key = trimmed.slice(0, colon).trim().toLowerCase();
    let value = trimmed.slice(colon + 1).trim();
    const hash = value.indexOf(' #');
    if (hash !== -1) value = value.slice(0, hash).trimEnd();
    meta[key] = value.replace(/^['"]|['"]$/g, '');
  }
  return { meta, body: normalised.slice(end + 4).replace(/^\n+/, ''), present: true };
}

const chatCustomers = new Set((settings.chat.customers ?? []).map((c) => String(c).toLowerCase()));

/**
 * Whether a tagged document belongs on the published site.
 *
 * Family pages and customer-specific pages are chat-only. The built site is
 * committed once and served by every customer's site, so it cannot differ per
 * customer without filtering at serve time; until it can, those pages are
 * reachable through the chatbot and nowhere else.
 */
function publishable(meta, present, name) {
  if (!present) return true;
  const audience = (meta.audience ?? 'staff').toLowerCase();
  const customer = (meta.customer ?? 'all').toLowerCase();
  // Public pages are for visitors, not staff: the site that uses this shows
  // them itself (tc9's /help), as it does family pages.
  if (audience === 'family' || audience === 'public') return { skip: true, why: `audience: ${audience}` };
  if (customer !== 'all') {
    if (settings.chat.enabled && chatCustomers.size && !chatCustomers.has(customer)) {
      warnings.push(`${name}.md is tagged customer "${customer}", which is not in chat.customers.`);
    }
    return { skip: true, why: `customer: ${customer}` };
  }
  return { skip: false };
}

const sources = [];
const chatOnly = [];
function addSource(name, slug, file) {
  const { meta, present } = readFrontmatter(fs.readFileSync(file, 'utf8'));
  const verdict = publishable(meta, present, name);
  if (verdict.skip) {
    chatOnly.push({ name, why: verdict.why });
    return;
  }
  sources.push({ name, slug, file });
}

if (fs.existsSync(path.join(root, 'README.md')) && !excluded.has('README')) {
  addSource('README', 'index', path.join(root, 'README.md'));
}
for (const f of fs.readdirSync(docsDir).sort()) {
  if (!f.endsWith('.md') || f.startsWith('.')) continue;
  const name = nameOf(f);
  if (excluded.has(name.toUpperCase())) continue;
  addSource(name, name, path.join(docsDir, f));
}

const siteOrder = upper(settings.site.order);
function sidebarOrder(name) {
  const i = siteOrder.indexOf(name.toUpperCase());
  return i >= 0 ? i + 1 : null;
}
// Pages not named in site.order follow the named ones, alphabetically.
const unordered = sources
  .filter((s) => sidebarOrder(s.name) === null)
  .map((s) => s.name)
  .sort();
const orderOf = (name) => sidebarOrder(name) ?? siteOrder.length + 1 + unordered.indexOf(name);

// ---------------------------------------------------------------------------
// Sidebar groups

// Each group is { label, pages, collapsed }. A group sits in the sidebar where
// the earliest of its pages falls in site.order, and lists its pages in the
// order it names them. Pages in no group stay at the top level.
const GROUP_KEYS = ['label', 'pages', 'collapsed'];
const groupWarnings = [];
const linkWarnings = [];
const published = new Set(sources.map((s) => s.name.toUpperCase()));
const groupOf = new Map();
const groups = [];
for (const [i, g] of (Array.isArray(settings.site.groups) ? settings.site.groups : []).entries()) {
  const at = `site.groups[${i}]`;
  if (!g || typeof g !== 'object' || typeof g.label !== 'string' || !Array.isArray(g.pages)) {
    groupWarnings.push(`${at} needs a label and a list of pages, and was ignored`);
    continue;
  }
  unknown.push(...Object.keys(g).filter((k) => !GROUP_KEYS.includes(k)).map((k) => `${at}.${k}`));
  const pages = [];
  for (const page of upper(g.pages)) {
    if (!published.has(page)) {
      groupWarnings.push(`${at} (${g.label}) names ${page}, which is not published`);
    } else if (groupOf.has(page)) {
      groupWarnings.push(`${page} is in two groups; it stays in ${groupOf.get(page).label}`);
    } else {
      pages.push(page);
    }
  }
  if (!pages.length) continue;
  const group = { label: g.label, collapsed: g.collapsed === true, pages };
  for (const page of pages) groupOf.set(page, group);
  groups.push(group);
}
if (!Array.isArray(settings.site.groups)) groupWarnings.push('site.groups has to be a list, and was ignored');

// ---------------------------------------------------------------------------
// Conversion

function yamlString(s) {
  return /^[\w][\w .,'()/&-]*$/.test(s) && !/: /.test(s) ? s : JSON.stringify(s);
}

// Only the page's opening paragraph counts, before any heading. A sentence
// from inside a section describes the section, not the page.
function firstSentence(body) {
  const intro = body.split(/^## /m)[0];
  for (const block of intro.split(/\n{2,}/)) {
    const t = block.trim();
    if (!t || /^(#|<|\{|\||-|\*|\d+\.|```|:::|>)/.test(t)) continue;
    const text = t.replace(/\s+/g, ' ').replace(/\[([^\]]+)\]\([^)]*\)/g, '$1');
    const m = text.match(/^(.+?[.!?])(\s|$)/);
    return m ? m[1] : null;
  }
  return null;
}

// Reorders, hides or flattens one tab group. Returns replacement lines.
function renderTabs(indent, key, tabs, used) {
  const cfg = settings.tabs[key] ?? { enabled: true, order: [], hide: [] };
  const hidden = new Set(cfg.hide ?? []);
  let visible = tabs.filter((t) => !hidden.has(t.label));
  const order = cfg.order ?? [];
  const rank = (label) => {
    const i = order.indexOf(label);
    return i >= 0 ? i : order.length;
  };
  visible = visible
    .map((t, i) => ({ ...t, i }))
    .sort((a, b) => rank(a.label) - rank(b.label) || a.i - b.i);

  if (visible.length === 0) return [];
  const flatten = visible.length === 1 || cfg.enabled === false || !settings.site.components.tabs;
  if (flatten) {
    // One path left, or tabs switched off: plain content. With several paths
    // and tabs off, each gets a bold label so the reader can tell them apart.
    const out = [];
    for (const t of visible) {
      if (visible.length > 1) out.push(`${indent}**${t.label}**`, '');
      out.push(...t.lines, '');
    }
    return out;
  }

  used.add('Tabs').add('TabItem');
  const out = [`${indent}<Tabs syncKey="${key}">`];
  for (const t of visible) {
    out.push(`${indent}  <TabItem label="${t.label}">`, '');
    out.push(...t.lines.map((l) => (l.trim() ? `  ${l}` : '')));
    out.push('', `${indent}  </TabItem>`);
  }
  out.push(`${indent}</Tabs>`);
  return out;
}

function convertTabs(lines, used) {
  const out = [];
  for (let i = 0; i < lines.length; i++) {
    const open = lines[i].match(/^([ \t]*)<!-- tabs:([\w-]+) -->\s*$/);
    if (!open) {
      out.push(lines[i]);
      continue;
    }
    const [, indent, key] = open;
    const tabs = [];
    let j = i + 1;
    for (; j < lines.length; j++) {
      if (lines[j].match(/^[ \t]*<!-- \/tabs -->\s*$/)) break;
      const tab = lines[j].match(/^[ \t]*<!-- tab:([^>]+?) -->\s*$/);
      if (tab) tabs.push({ label: tab[1].trim(), lines: [] });
      else if (tabs.length) tabs[tabs.length - 1].lines.push(lines[j]);
    }
    for (const t of tabs) {
      while (t.lines.length && !t.lines[0].trim()) t.lines.shift();
      while (t.lines.length && !t.lines.at(-1).trim()) t.lines.pop();
    }
    const rendered = renderTabs(indent, key, tabs, used);
    while (rendered.length && !rendered.at(-1).trim()) rendered.pop();
    out.push(...rendered);
    i = j;
  }
  return out;
}

function convert(source) {
  // Tags come off first. The provenance strip below is anchored to the start of
  // the file, so a frontmatter block left in place would push it out of reach
  // and the comment would be published as page content.
  let s = readFrontmatter(fs.readFileSync(source.file, 'utf8')).body;
  s = s.replace(/^<!--\nGenerated by client-docs[\s\S]*?-->\n\n/, '');
  const h1 = s.match(/^# (.+)\n\n?/m);
  const title = h1 ? h1[1].trim() : source.name;
  if (h1) s = s.slice(0, h1.index) + s.slice(h1.index + h1[0].length);

  const used = new Set();
  const components = settings.site.components;

  // Steps: an explicit <!-- steps --> region, or the list under "## Steps".
  if (components.steps) {
    if (/<!-- steps -->/.test(s)) {
      s = s.replace(/^([ \t]*)<!-- steps -->$/gm, '$1<Steps>')
        .replace(/^([ \t]*)<!-- \/steps -->$/gm, '$1</Steps>');
      used.add('Steps');
    } else {
      // (?![\s\S]) is end of file. A bare $ here would match any line end.
      const m = s.match(/^## Steps\n\n(1\. [\s\S]*?)(?=\n## |\n<!-- client-docs:generated:end|(?![\s\S]))/m);
      if (m) {
        const start = m.index + m[0].indexOf(m[1]);
        const body = m[1].replace(/\n+$/, '');
        s = s.slice(0, start) + `<Steps>\n\n${body}\n\n</Steps>\n` + s.slice(start + m[1].length);
        used.add('Steps');
      }
    }
  } else {
    s = s.replace(/^[ \t]*<!-- \/?steps -->\n/gm, '');
  }

  s = convertTabs(s.split('\n'), used).join('\n');

  // Links between documents. On disk, [X](CONFIGURATION.md) sits next to the
  // file it points at; on the site every page is a directory of its own, so the
  // link has to name that directory, relative to this page's. The home page is
  // one level up from the rest. A link to a document that is not published is
  // left alone and reported, because there is nothing on the site to point at.
  const up = source.slug === 'index' ? './' : '../';
  s = s.replace(/\]\(((?:\.\.?\/)*(?:docs\/)?([A-Za-z0-9_-]+)\.md)(#[^)\s]*)?\)/g, (whole, target, file, anchor = '') => {
    const name = file.toUpperCase() === 'README' ? 'README' : nameOf(`${file}.md`);
    const page = sources.find((p) => p.name.toUpperCase() === name.toUpperCase());
    if (!page) {
      linkWarnings.push(`${source.name} links to ${target}, which is not published`);
      return whole;
    }
    const href = page.slug === 'index' ? up : `${up}${page.slug.toLowerCase()}/`;
    return `](${href}${anchor})`;
  });

  if (components.filetree && /<!-- filetree -->/.test(s)) {
    s = s.replace(/^([ \t]*)<!-- filetree -->$/gm, '$1<FileTree>')
      .replace(/^([ \t]*)<!-- \/filetree -->$/gm, '$1</FileTree>');
    used.add('FileTree');
  } else {
    s = s.replace(/^[ \t]*<!-- \/?filetree -->\n/gm, '');
  }

  const fm = ['---', `title: ${yamlString(title)}`];
  // The home page is described by the project, whatever the setting for the
  // other pages, because Starlight shows it under the site's first heading.
  const projectDescription = manifest.project?.description;
  if (source.slug === 'index' && projectDescription) {
    fm.push(`description: ${yamlString(String(projectDescription).trim())}`);
  } else if (settings.site.descriptions === 'first-sentence') {
    const d = firstSentence(s.replace(/<!--[\s\S]*?-->/g, ''));
    if (d) fm.push(`description: ${yamlString(d)}`);
  }
  fm.push('sidebar:', `  order: ${orderOf(source.name)}`, '---', '');

  if (used.size === 0) {
    return { ext: '.md', text: fm.join('\n') + '\n' + s };
  }
  s = s.replace(/<!--\s*([\s\S]*?)\s*-->/g, '{/* $1 */}');
  const names = ['FileTree', 'Steps', 'TabItem', 'Tabs'].filter((c) => used.has(c));
  const imports = `import { ${names.join(', ')} } from '@astrojs/starlight/components';\n\n`;
  return { ext: '.mdx', text: fm.join('\n') + '\n' + imports + s };
}

// ---------------------------------------------------------------------------
// Open questions page

function openQuestionsPage(pageTitles) {
  const cfg = settings.site.open_questions;
  const open = manifest.documentation?.requires_confirmation ?? [];
  const esc = (t) => t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  const titleHtml = (t) => esc(t).replace(/`([^`]+)`/g, '<code>$1</code>');
  const mdx = (t) => t.replace(/\{/g, '&#123;').replace(/\}/g, '&#125;').replace(/</g, '&lt;');

  const target = (affects) => {
    const name = affects === 'README.md' ? 'README' : nameOf(affects ?? '');
    const page = pageTitles.get(name.toUpperCase());
    return page ? { ...page, name } : { title: affects ?? 'Other', href: null, name };
  };

  const position = cfg.position === 'first' ? 0 : typeof cfg.position === 'number' ? cfg.position : 100;
  const fm = ['---', `title: ${yamlString(cfg.title)}`,
    'description: What the repository cannot settle on its own, as a list to work through.',
    'sidebar:', `  order: ${position}`];
  if (cfg.badge) {
    fm.push('  badge:', `    text: "${open.length}"`, `    variant: ${open.length ? 'caution' : 'success'}`);
  }
  fm.push('---', '', "import { Card } from '@astrojs/starlight/components';", '');

  const body = [
    `${open.length} open. Each of these is something the code and configuration cannot show, ` +
      'so a person has to answer it. Answering one with `/docs-confirm` updates the page it ' +
      'affects and takes it off this list the next time the site is built.',
    '',
  ];
  if (!open.length) body.push('Nothing is waiting on an answer.', '');

  const sorted = open
    .map((item, i) => ({ item, i, page: target(item.affects) }))
    .sort((a, b) => orderOf(a.page.name) - orderOf(b.page.name) || a.i - b.i);
  let current = null;
  for (const { item, page } of sorted) {
    if (page.title !== current) {
      body.push(`## ${page.title}`, '');
      current = page.title;
    }
    body.push(`<Card title="${titleHtml(String(item.question))}" icon="${cfg.icon}">`, '');
    if (item.known) body.push(mdx(String(item.known).trim().replace(/\s+/g, ' ')), '');
    if (page.href) body.push(`Affects [${page.title}](${page.href}).`, '');
    body.push('</Card>', '');
  }
  return fm.join('\n') + '\n' + body.join('\n');
}

// ---------------------------------------------------------------------------
// Write

fs.mkdirSync(outDir, { recursive: true });
const written = new Set();
const pageTitles = new Map();
const report = [];

for (const source of sources) {
  const { ext, text } = convert(source);
  const file = source.slug + ext;
  fs.writeFileSync(path.join(outDir, file), text);
  written.add(file);
  const title = text.match(/^title: "?(.*?)"?$/m)[1];
  pageTitles.set(source.name.toUpperCase(), {
    title,
    href: source.slug === 'index' ? '../' : `../${source.slug.toLowerCase()}/`,
  });
  report.push(file);
}

if (settings.site.open_questions.enabled) {
  fs.writeFileSync(path.join(outDir, 'open-questions.mdx'), openQuestionsPage(pageTitles));
  written.add('open-questions.mdx');
  const n = manifest.documentation?.requires_confirmation?.length ?? 0;
  report.push(`open-questions.mdx (${n} open)`);
}

// The sidebar. Entries name pages by slug, so Starlight still takes each label
// and badge from the page's own frontmatter, including the open count.
const slugOf = (source) => (source.slug === 'index' ? 'index' : source.slug.toLowerCase());
const byName = new Map(sources.map((s) => [s.name.toUpperCase(), s]));
const topLevel = [];
const placed = new Set();
for (const source of [...sources].sort((a, b) => orderOf(a.name) - orderOf(b.name))) {
  const group = groupOf.get(source.name.toUpperCase());
  if (!group) {
    topLevel.push({ key: orderOf(source.name), entry: slugOf(source) });
  } else if (!placed.has(group)) {
    placed.add(group);
    topLevel.push({
      key: orderOf(source.name),
      entry: {
        label: group.label,
        collapsed: group.collapsed,
        items: group.pages.map((page) => slugOf(byName.get(page))),
      },
    });
  }
}
if (settings.site.open_questions.enabled) {
  const position = settings.site.open_questions.position;
  const key = position === 'first' ? 0 : typeof position === 'number' ? position : Infinity;
  topLevel.push({ key, entry: 'open-questions' });
}
topLevel.sort((a, b) => a.key - b.key);

const sidebarFile = outArg ? path.join(outDir, 'sidebar.mjs') : path.join(siteDir, 'src/sidebar.mjs');
fs.writeFileSync(sidebarFile, [
  '// Generated by client-docs from site.order and site.groups in',
  '// docs/client-docs.config.yml. Edit the settings and rebuild, not this file.',
  '// astro.config.mjs imports it as the Starlight `sidebar` option.',
  `export default ${JSON.stringify(topLevel.map((t) => t.entry), null, 2)};`,
  '',
].join('\n'));

const astroConfig = path.join(siteDir, 'astro.config.mjs');
const importsSidebar = fs.existsSync(astroConfig) && /sidebar\.mjs/.test(fs.readFileSync(astroConfig, 'utf8'));
if (groups.length && !importsSidebar) {
  groupWarnings.push(
    'site.groups is set, but astro.config.mjs does not import src/sidebar.mjs, so the sidebar stays flat. ' +
    "Add `import sidebar from './src/sidebar.mjs';` at the top and `sidebar,` inside starlight({ ... })",
  );
}

const removed = [];
for (const f of fs.readdirSync(outDir)) {
  if (/\.mdx?$/.test(f) && !written.has(f)) {
    fs.unlinkSync(path.join(outDir, f));
    removed.push(f);
  }
}

if (unknown.length) console.warn(`Unknown settings, ignored: ${unknown.join(', ')}`);
for (const w of groupWarnings) console.warn(`Sidebar: ${w}`);
for (const w of linkWarnings) console.warn(`Links: ${w}`);
for (const w of warnings) console.warn(`Tags: ${w}`);
if (chatOnly.length) {
  console.log(`Not published, available through the chatbot only:`);
  for (const p of chatOnly) console.log(`  ${p.name} (${p.why})`);
}
console.log(`Settings: ${fs.existsSync(configFile) ? 'docs/client-docs.config.yml' : 'plugin defaults (no docs/client-docs.config.yml)'}`);
console.log(`Wrote ${written.size} pages to ${path.relative(root, outDir) || outDir}:`);
for (const line of report) console.log(`  ${line}`);
if (removed.length) console.log(`Removed: ${removed.join(', ')}`);
console.log(`Sidebar: ${path.relative(root, sidebarFile)}, ${groups.length ? `${groups.length} groups` : 'no groups'}${importsSidebar ? '' : ' (not imported by astro.config.mjs)'}`);
