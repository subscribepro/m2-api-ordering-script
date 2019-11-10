# Magento 2 API Ordering Script

This script places an order via the Magento 2 REST API, allowing you to mock orders placed by Subscribe Pro.

## Configuration

Configuration must be done in the PHP file by updating the variables set in the index.php file. The following information is needed to configure the script:

* Magento 2 Base URL
* OAuth Consumer and Access keys
* Magento 2 Customer ID
* Product SKU, quantity, subscription ID and interval
* Shipping/Billing Address
* Shipping Carrier and Method
* Payment Profile ID
* Coupon Code (if applicable)

## Usage

Install dependencies with composer:

```bash
composer install
```

Run the script from a network-enabled shell with PHP installed:

```bash
php index.php
```
