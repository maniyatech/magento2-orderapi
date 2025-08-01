# ManiyaTech OrderApi module for Magento 2

The <b>Order API & Scheduled Export</b> module by <b>ManiyaTech</b> empowers Magento 2 store admins to efficiently <b>retrieve and export sales orders</b> using flexible filters. Orders can be pulled via a custom <b>REST API</b> or automatically exported via cron in either <b>CSV or XLSX</b> format. Files are saved in the var/exportorder/ directory, and <b>only the latest 5 files are retained</b> to optimize disk usage.

### Key Features

<ul>
	<li>✅ **REST API Support** : Retrieve a filtered list of orders using a custom REST endpoint.</li>
	<li>🔧 **Dynamic Filters**  : Admin-configurable filters for grand total and date range (past N days).</li>
	<li>📅 **Automated Cron Export**  : Scheduled order export jobs that generate Excel/CSV files and email them.</li>
	<li>📎 **Email Attachment Support**  : Exported files are automatically attached to emails based on schedule.</li>
	<li>📧 **Email Template Integration**  : Magento-compatible templates with dynamic fields (subject, receiver name, etc.).</li>
	<li>⚙️ **Admin Field Configuration**  : Define which order fields to export - includes custom attribute mapping.</li>
	<li>🧹 **Auto File Cleanup**  : Keeps only the 5 latest exports to save disk space.</li>
	<li>📂 **Multiple File Format Support**  : Choose between XLSX or CSV formats.</li>
	<li>📈 **Formatted Excel Output**  : Professionally formatted spreadsheets for reporting/analysis.</li>
	<li>🌐 **Timezone Aware Filtering**  : Date range filters work according to your Magento store's timezone.</li>
	<li>🔐 **Secure File Delivery**  : Uses Magento's built-in filesystem and email transport layers.</li>
	<li>🔄 **Magento 2.4.8 Compatible**  : Fully tested with Magento 2.4.8 and PHP 8.4.</li>
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
