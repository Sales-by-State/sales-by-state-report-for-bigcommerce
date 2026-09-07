=== Sales by State Report for BigCommerce ===
Contributors: BusinessBloomer
Donate link: https://salesbystate.com/
Tags: sales-report, sales-by-state, bigcommerce, analytics, sales-tax
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See a yearly breakdown of BigCommerce sales by state / county / province for a given country, filterable by order status.

== Description ==

Sales by State Report for BigCommerce adds a report showing net and gross sales grouped by state, county, or province, for a chosen year and a chosen set of order statuses.

It appears under **Products → Sales by State**.

Use it when you need to know how much each state bought in a given year, counting only the order statuses that matter for sales tax or territory planning.

This plugin requires [BigCommerce for WordPress](https://wordpress.org/plugins/bigcommerce/). There are no settings screens to configure. After you activate the plugin, open the report and choose a country, year, and order statuses.

Documentation: [salesbystate.com](https://salesbystate.com/)

= How to use =

1. Install and activate BigCommerce for WordPress, connect a store, then install and activate this plugin.
2. Go to **Products → Sales by State**.
3. Choose a **country**, a **year**, and the **order statuses** that should count.
4. The table lists Net Sales and Gross Sales for every state in that country.

If the store already has orders, the plugin imports them from the BigCommerce API into its report table in the background. A progress bar appears until that finishes. You can leave the page; the import continues on its own.

= What the report shows =

* Net Sales and Gross Sales for every state in the selected country
* A summary of both figures across all states
* Sortable columns and paginated results
* States with no sales, shown as zero rather than hidden

= Filters =

* **Country** — United States, Canada, and United Kingdom. Defaults to the United States.
* **Year** — a rolling list that starts ten years back and gains a year each January without dropping one. Defaults to the current year.
* **Order status** — a checkbox list of BigCommerce order statuses. Defaults to Completed.

= How the figures are calculated =

Gross Sales is the order total including tax. Net Sales is that total minus tax and shipping (excluding tax). Both use the values BigCommerce stores on the order.

Refunds are not modelled as separate records. An order that has been fully refunded is controlled by the status filter. A partial refund is not deducted from its order's total.

= Performance =

Sales for a whole year are answered by one indexed query that returns one row per state. The response size does not grow with the number of orders.

BigCommerce keeps orders on its API, not in a local WordPress table. Existing orders are imported from the BigCommerce API into the report table once. After that import, opening or changing the report filters does not call the API. New orders are picked up by a background poll of the most recent orders.

= Data and privacy =

The plugin creates one custom database table holding, per order: the order ID, order status, creation and payment dates, billing and shipping country and state codes, currency, and the order, tax, shipping and net totals. It stores no names, addresses, email addresses or any other personal data.

During the one-off import, and when recent orders are polled, the plugin reads order data from the BigCommerce API using the store credentials already saved by BigCommerce for WordPress. It does not send data to any other service, includes no third-party analytics, and collects no telemetry.

Deleting the plugin removes the table and its options.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/sales-by-state-report-for-bigcommerce`, or install it through the Plugins screen.
2. Activate the plugin. BigCommerce for WordPress must already be installed and active, with a store connected.
3. Go to **Products → Sales by State**.

On a store that already has orders, those orders are read from the BigCommerce API into the report table once. This starts on its own. If it has not finished when you open the report, a progress bar shows how far along it is.

== Frequently Asked Questions ==

= The report shows zeros but I have orders. =

Your existing orders are still being read into the report table. Open the report and the progress bar will show how far along it is. It continues on its own; you can leave the page.

If only Completed is selected, tick any other statuses that should count.

To confirm how much of the import has finished, open **Tools → Site Health → Info → Sales by State Report for BigCommerce**.

= Where does the report appear? =

Under **Products → Sales by State**. Users who can manage options can open it.

= Which address does it group by? =

The shipping address, falling back to the billing address for orders that have no shipping address on the API payload.

= Are refunds deducted? =

The status filter decides whether an order counts. Partial refunds are not deducted from the order's total.

= Which date does the year filter use? =

The order created date. Orders in a sold status also store that date as the paid date.

= Can I change the default order status? =

Yes, with the `sbsbc_default_statuses` filter.

= Where can I get support? =

Use the [WordPress.org support forum](https://wordpress.org/support/plugin/sales-by-state-report-for-bigcommerce/) for this plugin.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
