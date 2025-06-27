# ManiyaTech OrderApi module for Magento 2

The Order API & Scheduled Export module by ManiyaTech allows merchants to access and export order data efficiently using configurable filters. You can retrieve orders via REST API based on a minimum grand total and a selected date range. In addition, a cron job can be scheduled to automatically export orders to Excel format and store them in the var/exportorder/ directory. The module keeps only the latest 5 export files, ensuring disk space optimization.

This is especially useful for admins who need regular exports for reporting, analysis, or integration with third-party systems.

### Key Features

<ul>
	<li>✅ REST API Support: Fetch filtered order list using custom API endpoint.</li>
	<li>🔧 Configurable Filters: Set minimum grand total and number of past days for export.</li>
	<li>📅 Scheduled Export via Cron: Automatically generate Excel files on a defined schedule.</li>
	<li>📂 Auto Cleanup: Keeps only the 5 most recent export files; older files are automatically deleted.</li>
	<li>📈 Formatted Excel Output: Generates well-structured spreadsheets with all key order fields.</li>
	<li>🛡️ Magento Standards Compliant: Follows Magento 2.4.X and PHP 8.4 best practices with PSR & PHPCS compatibility.</li>
	<li>⚙️ Admin Configurable: Enable/disable module, define export filters, and cron frequency from the backend.</li>
	<li>🕒 Timezone Aware: Date range filters work according to store timezone settings.</li>
</ul>

## How to install ManiyaTech_OrderApi module

### Composer Installation

Run the following command in Magento 2 root directory to install ManiyaTech_OrderApi module via composer.

#### Install

```
composer require maniyatech/magento2-orderapi
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy -f
```

#### Update

```
composer update maniyatech/magento2-orderapi
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy -f
```

Run below command if your store is in the production mode:

```
php bin/magento setup:di:compile
```

### Manual Installation

If you prefer to install this module manually, kindly follow the steps described below - 

- Download the latest version [here](https://github.com/maniyatech/magento2-orderapi/archive/refs/heads/main.zip) 
- Create a folder path like this `app/code/ManiyaTech/OrderApi` and extract the `main.zip` file into it.
- Navigate to Magento root directory and execute the below commands.

```
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy -f
```
