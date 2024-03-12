# sevDesk Stock Sync for WordPress
This is a very simple WordPress plugin that retrieves product stock quantities via sevDesk API and writes them to a CSV file once every hour.

It can be used in combination with WooCommerce or any other ecommerce plugin for WordPress.

* CSV file location: `wp-content/uploads/sevdesk-stock-sync/stock_quantities.csv`
* Log file location: `wp-content/uploads/sevdesk-stock-sync/sevdesk_stock_sync.log`

The sevDesk API token can be entered and stored under `Settings / sevDesk Stock Sync` in WordPress Admin.

In order to update your products, you need another plugin like https://wordpress.org/plugins/wp-all-import/.
