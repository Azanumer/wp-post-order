# Post Order

Drag-and-drop ordering for posts, pages and custom post types — set a manual order in the admin list and have the front end honour it automatically.

## Features

- **Drag-and-drop** — reorder rows directly on the post list screen (jQuery UI Sortable, already bundled with WordPress); the order saves via AJAX as `menu_order` values.
- **Front-end ordering** — the main query for an ordered post type lists items by `menu_order` automatically, unless the theme sets an explicit `orderby`.
- **Order column** — a sortable "Order" column on every ordered post type's list table, so you can see and sort by position.
- **Per-type control** — choose which post types get ordering under **Settings → Post Order** (posts and pages by default). Hierarchical types like pages already support ordering; this adds the same behaviour to flat types such as posts.
- **Safe by design** — sorting only activates on the plain list view (not while searching, filtering or sorting by another column); the AJAX handler checks a nonce, the `edit_posts` capability, and per-post edit rights.

## Installation

1. Upload the `wp-post-order` folder to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins** in WordPress admin.
3. Pick your post types under **Settings → Post Order**, then drag rows on the post list screen.

## Filters

- `wpo_post_types` — override the list of ordered post types in code.

## Uninstall

Deleting the plugin removes the `wpo_settings` option. The `menu_order` values stay on posts (harmless — WordPress ignores them unless a query orders by them).

## License

MIT — see [LICENSE](LICENSE).
