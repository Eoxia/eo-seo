# EO SEO

A complete and lightweight SEO plugin for WordPress, published on wordpress.org. Works on its own, and gives the AI connected to [EO Tools](https://github.com/Eoxia/eo-tools) the abilities to audit the site and propose SEO changes, always applied by a person.

Roadmap: [issue #1](https://github.com/Eoxia/eo-seo/issues/1).

## Requirements

WordPress 7.0+ (Abilities API), PHP 8.1+.

## Architecture

| Folder | Content |
|---|---|
| `includes/` | Business logic, namespace `EoSeo` (one class per file, `class-*.php`) |
| `admin/` | Admin screens and form handlers, namespace `EoSeo\Admin`; `admin/views/` holds the templates (display only) |
| `blocks/` | "Breadcrumbs" block, rendered on the server |
| `assets/` | Admin CSS and plain JS (no build step: the scripts use the WordPress globals) |
| `languages/` | `.pot` and the fr_FR translation (`.po`, `.l10n.php`, script JSON files) |

Two registries are the single source of truth:

- `EoSeo\Fields`: SEO fields (metas `_eo_seo_*`, editor panel, term form, front tags, ability schemas). Filter `eo_seo_fields`.
- `EoSeo\Checks`: content checks (indicator, editor, dashboard, audit abilities). Filter `eo_seo_checks`.

Other hooks: `eo_seo_schema_graph`, `eo_seo_breadcrumbs`, `eo_seo_current_meta`, `eo_seo_site_audit`, `eo_seo_conflicting_plugins`.

## Abilities (for EO Tools)

Category `eo-seo`, `mcp.public` false, risk level in `meta.eo_tools`: `audit-site`, `audit-post`, `list-issues`, `list-404`, `get-settings` (read), `update-draft-seo` (write), `propose-seo`, `propose-redirections` (sensitive: proposals applied by a human in EO SEO › Proposals, or EO Tools › To validate through `eo_tools_mcp_proposal_types`).

## Checks before a commit

```sh
php wp-content/plugins/plugin-check/vendor/bin/phpcs --standard=wp-content/plugins/plugin-check/phpcs-rulesets/plugin-review.xml wp-content/plugins/eo-seo
wp plugin check eo-seo
```

## Release

Pushing a tag `x.y.z` publishes the plugin to the wordpress.org SVN (`.github/workflows/deploy.yml`, secrets `SVN_USERNAME` and `SVN_PASSWORD`). Files listed in `.distignore` are not published.
