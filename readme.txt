=== Native Tables & Charts ===
Contributors: cgm
Tags: gutenberg, tables, charts, data, responsive, benchmark
Requires at least: 6.6
Requires PHP: 8.1
Stable tag: 3.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build responsive tables and charts directly in Gutenberg, reuse datasets across posts, and migrate League Table content without relying on shortcodes or external chart services.

== Description ==

Native Tables & Charts is a Gutenberg-first data presentation plugin for editorial WordPress sites. It combines a spreadsheet-style data editor, responsive semantic tables, native charts, reusable datasets, style presets, import/export tools, and a migration path from League Table.

The primary workflow lives inside the block editor. Add a Native Data Table or Native Data Chart block, enter or paste data, choose a template, adjust responsive presentation, and publish. No shortcode is required for new content.

Charts are rendered locally by WordPress using HTML, CSS and SVG. The plugin does not call an external chart API or require a CDN. Every chart also includes an accessible HTML data representation.

= Gutenberg blocks =

* Native Data Table
* Native Data Chart

Inserter variations include Blank Data Table, Product Comparison, Specifications, Ranking, Benchmark Results, Horizontal Benchmark Chart, Dual-Metric Benchmark Chart, and Grouped Comparison Chart.

= Data workflow =

* Inline datasets for data used only in the current block.
* Reusable datasets for data shared between posts and visualizations.
* Synced views for table/chart presentation settings that should be reused.
* Detach/snapshot workflow when a historical copy should stop receiving synced data updates.
* Data Library with search, usage counts, view counts, raw data export, full dataset/view bundle export, duplication and deletion.
* Maximum supported dataset size: 10,000 rows and 40 columns.
* Virtualized Gutenberg grid for large datasets.
* Debounced row updates for reusable datasets rather than rewriting the full dataset on every cell edit.

= Table features =

* Direct spreadsheet-style editing in Gutenberg.
* Add, insert, duplicate, move and delete rows and columns.
* Rectangular cell selection, copy, cut, clear and multi-cell paste.
* Paste from Excel and Google Sheets.
* CSV, TSV and JSON import/export.
* Header row, caption and automatic position/ranking column.
* Up to five default sort priorities.
* Optional visitor sorting with no jQuery dependency.
* Sorting types: automatic, text, number/digit, percent, currency, URL, time, ISO date, US long date and short date.
* Short date formats: DD/MM/YYYY, YYYY/MM/DD and MM/DD/YYYY.
* US/point-decimal and EU/comma-decimal number handling.
* Sticky headers.
* Automatic or fixed table layout.
* Table, column and scroll-container widths/heights.
* Desktop/tablet/mobile preview in Gutenberg.
* Responsive horizontal scroll, hidden-column and stacked-card modes.
* Separate phone/tablet breakpoints, typography and image visibility.
* Column alignment and width controls.
* Row/column automatic colour rules and alignment rules.
* Header/body/caption font family, weight, style, size and colour controls.
* Header, odd-row and even-row background/text/link colours.
* Borders, cell spacing, radius and margins.
* Per-cell text/background colour, alignment, font weight and font style.
* Per-cell links with optional new-tab behaviour.
* WordPress Media Library images on the left or right side of a cell, with alt text and optional image links.
* Merged cells using row/column spans.
* Custom HTML cells are always sanitized. Administrators can use WordPress' normal post HTML/protocol allow-list or configure a League Table-style restricted tag/protocol list under Settings. Developers can extend the result with the `ntc_allowed_html` and `ntc_allowed_protocols` filters.
* Formula operations: sum, subtraction, minimum, maximum and average.
* Average decimal precision and PHP-compatible half-up, half-down, half-even and half-odd rounding.
* Built-in table style presets: Editorial, Comparison, Ranking, Specifications, Minimal, Compact and Dark.
* Save, import and export custom table style presets.

= Chart features =

* Horizontal bar chart.
* Dual-metric benchmark chart with independent metric scales.
* Vertical bar chart.
* Grouped bar chart.
* Stacked bar chart.
* Multi-series line chart.
* Scatter-style point chart.
* Donut chart.
* Combo, histogram, box-and-whisker, waterfall, bullet, bubble, funnel, range-bar, timeline/Gantt and slope charts.
* Treemap, sunburst, Sankey, candlestick/OHLC, error-bar, calendar-heatmap and population-pyramid charts.
* Likert, Pareto, streamgraph, parallel-coordinates, network, choropleth/region-map and polar-area charts.
* Gutenberg data editing without an external chart service. Charts default to a clean Preview mode, with dedicated Data, optional Split, and full-screen Focus modes.
* Responsive layouts based on the chart container, with a configurable mobile breakpoint.
* Automatic-height charts by default, with optional 16:9, 4:3 and square aspect ratios.
* Responsive numeric axes and optional benchmark grid lines for horizontal and dual-metric charts.
* Mode-based Gutenberg preview with Desktop/Tablet/Mobile viewport controls; charts reopen in clean Preview mode instead of showing the spreadsheet and visualization at the same time.
* Titles, subtitles, direction labels, legend labels, sort annotations and axis labels.
* Higher-is-better, lower-is-better and neutral presentation.
* Data sorting without destructively reordering the stored dataset.
* Units and decimal formatting.
* Exact row labels can be highlighted, useful for emphasizing the product being reviewed.
* Primary, secondary and highlight colours.
* Typography presets plus direct sizing controls for chart titles, subtitles, row labels, values, axes, legends and footers.
* Auto/Spacious/Comfortable/Compact chart-density modes for intelligent row and bar sizing.
* Multi-series legend for grouped, stacked, line and scatter charts.
* Footer, secondary footer and source fields for benchmark/system configuration notes.
* Accessible chart labels and a semantic HTML data representation with Screen readers only, Collapsible, Always visible and Disabled output modes.
* Built-in chart themes include benchmark, editorial, dashboard, accessible, print/grayscale, financial, scientific, soft-neutral, high-impact dark, brand-inherit and compact-mobile treatments, with visual previews in Gutenberg.
* Save, import and export custom chart style presets.

= Editor settings =

Administrators can individually enable or disable advanced cell-property controls, including colours, alignment, typography, links, images, formulas, custom HTML and row/column spans. This mirrors League Table's ability to limit which cell tools are exposed while keeping the Gutenberg interface simpler for editors. The same Settings screen can restrict which HTML tags, attributes and link protocols custom HTML cells are allowed to use.


= Native backup and portability =

* Tables & Charts > Tools can export a complete JSON backup of all reusable datasets and synced table/chart views.
* Custom presets are included in complete backups when the current user has preset-management permission.
* Each reusable dataset can also be exported as a self-contained bundle containing its data and synced views.
* Complete backups, dataset bundles and raw Native Tables & Charts JSON data exports can be imported as new records without overwriting existing datasets.
* CSV, TSV and JSON data exports remain available from the Gutenberg editor and REST-backed Data Library workflow.

= League Table migration =

The plugin includes a migration tool designed around League Table 2.25 data structures.

* Detects League Table database tables.
* Dry-run report before migration.
* Migrates table data, captions, descriptions, enabled sorting rules, ranking, widths, typography, colours, borders, responsive options, cell links, cell images, formulas, merged cells, HTML cells, autocolours and autoalignment.
* Imports League Table XML exports.
* Converts `[lt id="..."]` shortcodes to Native Data Table blocks.
* Converts existing `dalt/table` Gutenberg blocks to Native Data Table blocks.
* Stores original post content in migration backups before replacement.
* Can roll back the post-content changes from a migration batch.
* Migrates League Table's configured cell-property visibility switches and custom-HTML tag/protocol allow-lists when Native Tables & Charts has not already been configured. For security, custom HTML remains sanitized even if the legacy plugin had its KSES bypass disabled.
* Never deletes the original League Table database tables.

Always perform a full database/file backup and run the dry-run on a staging copy before migrating a production site.

== Installation ==

1. Upload the `native-tables-charts` folder to `/wp-content/plugins/`, or install the ZIP from Plugins > Add New > Upload Plugin.
2. Activate Native Tables & Charts.
3. Open a post or page in the block editor and add a Native Data Table or Native Data Chart block.
4. For reusable data, use the block toolbar to save the current inline data as a reusable dataset.
5. Manage reusable datasets under Tables & Charts > Data Library.
6. Manage custom styles under Tables & Charts > Style Presets.
7. Use Tables & Charts > Tools for full native backups and re-import.
8. If replacing League Table, open Tables & Charts > Migration and run a dry-run first.

== Migrating from League Table ==

1. Back up WordPress and the database.
2. Install and activate Native Tables & Charts while League Table data is still present.
3. Go to Tables & Charts > Migration.
4. Run the Dry-Run Report and review the detected tables, post instances and advanced cells.
5. Run Migration. Leave content replacement enabled if you want old shortcodes and League Table blocks converted automatically.
6. Review migrated posts and responsive behaviour on staging.
7. Only after verification, disable League Table.
8. Leave the original League Table database tables in place until you are satisfied with the migration.

== Frequently Asked Questions ==

= Does the plugin require shortcodes? =

No. New tables and charts are native Gutenberg blocks. The migration tool can replace old League Table shortcodes with blocks.

= Does chart rendering depend on a third-party service? =

No. Charts are rendered by WordPress using local HTML, CSS and SVG output. No chart data is sent to an external rendering service.

= What happens if JavaScript is disabled on the front end? =

Tables and charts remain server-rendered and readable. Visitor-triggered table re-sorting is the main enhancement that requires the small local front-end script. Charts include an HTML data representation.

= Can the same data power a table and several charts? =

Yes. Save the data as a reusable dataset and reference it from multiple table/chart blocks or synced views.

= Can I freeze a historical chart so future dataset edits do not change it? =

Yes. Detach the block from the reusable dataset to create an inline snapshot.

= How large can a dataset be? =

Version 1.0 limits datasets to 10,000 rows and 40 columns. The Gutenberg data grid virtualizes large row sets so it does not mount every visible cell at once.

= Does uninstalling delete my datasets? =

Not by default. Tables & Charts > Settings contains an explicit option to delete plugin data on uninstall. Keep it disabled on production unless you intentionally want all plugin data removed.

== Changelog ==

= 3.2.2 =
* Added mouse-driven table column resizing with live header and cell feedback, fixed-layout persistence, and larger coarse-pointer targets.
* Added keyboard column resizing with arrow keys, Shift for larger steps, and Home to restore the default width.
* Added a dedicated Column widths dialog with per-column units, validation, individual resets, Reset all, and table-layout controls.
* Synchronized dragged widths with the Selected Column sidebar and server-rendered table preview.

= 3.2.1 =
* Reworked the Data Library with responsive mobile cards, labeled search, clearer empty states, and organized dataset, source, and delete actions.
* Added preset swatches, actionable empty states, clearer import and migration guidance, and grouped settings with per-group bulk controls.
* Replaced editor naming prompts with accessible dialogs and improved grid labels, delete targets, chart search, target mapping, and import recovery.
* Added automatic cache busting for admin stylesheet changes.

= 3.2.0 =
* Added sub-heading rows so tables can be split into named sections for spec sheets and other structured layouts.
* Added two heading displays: a full-width banner row and a side-label column that spans its section, plus optional heading background and text colours.
* Added optional visitor expand/collapse toggles per section, with a start-collapsed setting.
* Section tables keep groups together by suspending sorting and pagination, and position numbering skips heading rows.
* Added a ready-made Spec Sheet block pattern with seven collapsible side-label sections.

= 3.1.0 =
* Added block transforms so the standard Gutenberg Table block and Flexible Table Block can be converted to Native Data Table directly in the editor.
* Preserves headers, captions, fixed layout, stacked-mobile and sticky-header behaviour, merged cells, cell alignment, colours, font styling, links and inline HTML during conversion.
* Converts numeric columns to native numeric types so sorting works immediately.

= 3.0.9 =
* Added progressive large-table rendering so only one page of rows is mounted in the browser at a time.
* Added chart-type-aware render limits, evenly sampled trend data, and an explicit maximum-row control.
* Reduced Gutenberg data duplication with shared reusable-dataset caches, larger REST chunks, and lightweight saved block attributes.
* Reused dataset, view, source, and row reads within each frontend request and reduced unnecessary post-source cache invalidation.
* Reworked League Table detection into resumable 100-post requests and reduced migration work to five identified posts per request.
* Added complete opt-in Dataset and Review JSON-LD controls with validation, provenance, licensing, distribution, product, author, publisher, dates, pros/cons, and safe aggregate-rating rules.
* Prevented incomplete structured data from being emitted and added the `ntc_schema_payload` integration filter.

= 3.0.8 =
* Fixed a React lifecycle error that could crash Gutenberg after opening or closing the existing-data picker.
* Loaded reusable datasets and synced views completely before applying them, preventing partial or broken block previews.
* Added spreadsheet-style cell navigation with caret-aware arrow keys, Enter, Shift+Enter, and Tab.
* Realigned and respaced the existing-data and import modals across desktop and narrow editor layouts.

= 3.0.7 =
* Replaced the complete Gutenberg Shortcode block when migrating an embedded League Table shortcode.
* Detected and repaired native table blocks incorrectly nested inside Shortcode blocks by version 3.0.6.
* Invalidated affected migration progress so damaged posts are rediscovered before continuation.

= 3.0.6 =
* Split large legacy table row and cell-property imports into resumable 200-record requests.
* Replaced legacy shortcodes without running expensive third-party post-save hooks while preserving rollback backups and clearing post caches.
* Added detailed table-stage progress and paused automatic retries when a migration step reports an error.

= 3.0.5 =
* Added migration state versioning so incompatible saved progress is cleared before continuation.
* Prevented stale migration auto-submits from rebuilding target detection inside proxied requests.
* Restored fresh post and shortcode counts after upgrades and added a Restart Migration Detection control.

= 3.0.4 =
* Snapshotted the exact post IDs found during legacy shortcode and block detection.
* Replaced site-wide post scanning with direct, resumable 20-post target queries.
* Restored identified post and shortcode totals throughout table import and post replacement.
* Added safe continuation for migrations started by earlier 3.0.x releases.

= 3.0.3 =
* Split legacy table import and post replacement into separate resumable request phases.
* Limited each request to one previously-unmigrated legacy table so large migrations can progress behind reverse proxies.
* Replaced wildcard post-content searches with bounded primary-key scan windows while retaining the 20-update request limit.
* Improved migration phase progress and preserved the original shortcode-replacement choice across continuation requests.

= 3.0.2 =
* Reworked shortcode replacement into bounded, resumable batches to avoid proxy and Cloudflare timeouts.
* Replaced offset pagination with post-ID cursors so shrinking migration result sets cannot skip posts.
* Reduced continuation-page database work while preserving migration progress, backups and rollback support.

= 3.0.1 =
* Aligned Gutenberg data-grid headers with their editable body columns by normalizing box sizing and inherited input margins.
* Made content-width tables use the same visible width as sibling charts while preserving cell and frontend-control padding.
* Removed the extra table-preview inset in Gutenberg Preview mode.
* Added file-versioned frontend assets so table and chart layout corrections are not hidden by stale browser caches.

= 3.0.0 =
* Added 24 specialized native chart renderers: combo, histogram, boxplot, waterfall, bullet, bubble, funnel, range, timeline, slope, treemap, sunburst, Sankey, candlestick, error bars, calendar heatmap, population pyramid, Likert, Pareto, streamgraph, parallel coordinates, network, region choropleth and polar area.
* Expanded the Gutenberg chart chooser with question-based groups, chart-specific data mapping guidance and validation.
* Added Dashboard, Accessible, Print Grayscale, Financial, Scientific, Soft Neutral, High Impact Dark, Brand Inherit and Compact Mobile chart themes.
* Added responsive presentation and PNG export support for the advanced SVG chart family.
* Removed the unexplained table outline by default and added explicit outer-frame width, colour and radius settings.
* Includes the 2.0.1–2.0.3 chart workspace, responsive table layout and frontend-control corrections.

= 2.0.3 =
* Corrected table box sizing so bordered previews remain centered without pushing past their right edge.
* Made chart and table editor workspaces respond to their actual block width, including nested and narrow Gutenberg canvases.
* Improved compact editor controls for tablet and mobile widths.
* Fixed Gutenberg previews so table search, pagination and CSV controls immediately follow their inspector settings.

= 2.0.2 =
* Inactive chart and table blocks now show only their published-content preview, including inside Gutenberg's transform menu.
* Added a clearly labelled table workspace with Data, Preview, responsive preview and Style controls.
* Collapsed advanced cell settings behind an intentional control and removed unnecessary horizontal scrollbars from simple tables.

= 2.0.1 =
* Reworked the Native Data Chart editor around a clear Preview, Data and Split workspace.
* Added an intent-based chart type browser, consolidated data mapping, chart setup validation, and responsive preview controls.
* Reduced block-toolbar clutter and moved chart colour settings into the Gutenberg Styles tab.

= 2.0.0 =
* Major chart expansion: tooltips, date/time x-axis, area, radar, gauge, change, dumbbell, small multiples and heatmap chart types.
* Analysis features: reference lines, top-N with "Others", conditional series colour rules, legend toggles, annotations, and a range brush for line charts.
* Review workflow: Review Card block pattern and schema.org Review/aggregateRating structured data.
* Data depth: post-driven datasets (live charts from post meta) with caching.
* Conditional series colour rules on multi-row line and area charts colour the whole stroke by the first point's value.
* Grouped and stacked bars no longer carry ntc-series-N classes; custom CSS targeting those classes must be updated.

= 1.1.1 =
* Replaced the dataset/view picker with a selectable list (select + confirm, double-click to pick immediately).
* Batched multi-row dataset writes — up to 500 rows per INSERT statement, dramatically faster imports, migration and XML import.
* League Table migration and rollback now process posts in bounded chunks with automatic resumption and progress display, avoiding PHP timeouts on large sites.

= 1.1.0 =
* New opt-in features: Schema.org Dataset JSON-LD, sparkline and delta-badge column types, frontend table search and pagination, heatmap colour scales, remote CSV/TSV dataset sync via WP-Cron, automatic dark chart theme, frontend CSV/PNG export buttons, classic-editor `[ntc_dataset]` shortcode, and last-updated captions for dataset-backed blocks.
* Best-practice fixes: REST argument validation, CSV formula-injection guards, preset slug collisions no longer overwrite existing presets, presets list now requires edit capability, view creation validates the dataset, orphan migration options are cleaned up on uninstall, cron events are cleared on deactivate/uninstall, arrow-key navigation in the data grid.
* All PHP now passes the WordPress-Core coding standard.

= 1.0.8 =
* Added GPLv2 license.txt and enabled JavaScript translations for the block editor.
* Added a database upgrade check on load so table-schema changes are applied when the plugin updates, not only on fresh activation.
* Preserved literal cell data (for example `x < y`) when saving reusable datasets; values were previously run through a post-content sanitizer.
* Table sidebar controls now show the resolved style-preset values instead of empty fields.
* Hardened the rendering layer against CSS injection through block settings and cell properties.
* Removed unused configuration keys and an unimplemented expression evaluator.

= 1.0.7 =
* Added a full chart-presentation pass focused on readability, theme discovery and frontend data-table control.
* New chart typography presets: Compact, Comfortable and Presentation, with individual controls for title, subtitle, product/category labels, values, axes, legends and footers.
* New responsive typography variables keep chart text readable instead of scaling all text down with the visualization.
* Added Auto, Spacious, Comfortable, Compact and Custom density modes. Auto adjusts bar height and spacing to dataset size.
* The public “View chart data” control is no longer forced on every chart. New modes are Screen readers only (default), Collapsible, Always visible and Disabled.
* Added visual theme browsing with miniature previews in the Gutenberg Inspector and quick Style editor.
* Expanded built-in chart themes to Benchmark Dark, Benchmark Light, Benchmark Compact, Editorial Light, Editorial Dark, Minimal, High Contrast, Feature, Comparison and Technical.
* Renamed chart type/style concepts in the editor to Chart Layout and Chart Theme so structure and appearance are clearly separated.
* Table style selection now also uses visual preset previews.

= 1.0.5 =
* Moved chart-focus controls into a dedicated focus gutter beside the row-number gutter so the star can never overlap the row number.
* Replaced text star glyphs with WordPress Dashicons for consistent sizing and alignment.
* Focus controls are always visible in chart Data mode, with active rows using the chart highlight accent.
* Removed the redundant hover delete control from the row-number gutter; row deletion remains available in the contextual row toolbar.

= 1.0.4 =
* Replaced Gutenberg's ambiguous None/Wide/Full alignment UI with an explicit Content width/Wide width/Full width control for Native Table and Chart blocks.
* Content width is now the default and follows the theme's normal article content size in the editor and on the front end.
* Removed the editor-root width rule that could override Gutenberg's normal content-column constraint.
* Wide and Full are now clearly opt-in breakout layouts rather than looking like horizontal alignment choices.
* Added a Layout panel in the block Inspector explaining the three width modes.
* Existing 1.0.2 wide/full choices are recognized through the legacy align attribute when opening older blocks.

= 1.0.2 =
* Fixed Gutenberg block wrapper integration so the native block toolbar, alignment controls, selection outline, and Inspector sidebar work correctly.
* Charts and tables now use the editor content width by default instead of expanding across the full editor canvas. Wide and full alignments remain available through Gutenberg.
* Added a dedicated Styles tab in the block Inspector for chart and table appearance controls.
* Added a Style button to the block toolbar for quick appearance editing.
* Dynamic frontend wrappers now use WordPress block wrapper attributes so alignment and block-support spacing are rendered correctly on published posts.

= 1.0.1 =
* Rebuilt the Gutenberg authoring experience around Preview, Data, Split and Focus modes.
* Native Data Chart blocks now reopen in Preview mode so article editing stays visually close to the published post.
* Data mode replaces the visualization while editing instead of stacking the spreadsheet above a second chart preview.
* Added optional side-by-side Split mode for power users.
* Added a large Focus Mode modal for complex or wide datasets.
* Moved Desktop/Tablet/Mobile responsive previews onto the active visualization rather than rendering a second preview section.
* Replaced the permanent multi-row action bars with a compact data toolbar, contextual selection actions and a More menu.
* Added shared-dataset warnings when editing reusable or synced data.
* Added data summary/status controls beneath chart/table previews and quick Edit Data / Focus Mode actions in the chart inspector.
* Table blocks default to Data mode but can switch to a clean frontend Preview when needed.

= 1.0.0 =
* Initial release.
* Gutenberg-native responsive table and chart blocks.
* Inline/reusable dataset architecture and synced views.
* Responsive benchmark chart templates.
* Spreadsheet-style Gutenberg data editor.
* Table sorting, formulas, merged cells, cell styling, links, images and responsive controls.
* Style preset system.
* CSV/TSV/JSON import/export plus complete native dataset/view/preset backup bundles.
* Configurable custom-HTML allow-list with mandatory sanitization.
* League Table 2.25 database/XML/content migration with dry-run and rollback.
