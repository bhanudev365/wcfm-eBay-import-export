<?php

/**
 * Fired during plugin activation
 *
 * @link       http://devstellen.pw/
 * @since      1.0.0
 *
 * @package    Wc_Ebay_Import_Export
 * @subpackage Wc_Ebay_Import_Export/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Wc_Ebay_Import_Export
 * @subpackage Wc_Ebay_Import_Export/includes
 * @author     Bhanu <bhanu.stelleninfotech@gmail.com>
 */
class Wcfm_eBay_Import_Export_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		global $wpdb;
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		$modified_products = $wpdb->prefix . 'wcfm_ie_modified_products';
		$table_name = $wpdb->prefix . 'wc_ebay_multiseller_details';
		$cat_table_name = $wpdb->prefix . 'api_category';
		$request_table_name = $wpdb->prefix . 'wcfm_ie_request';
		$cron_request = $wpdb->prefix . 'wcfm_ie_cron_request';
		$cron_request_errorlog = $wpdb->prefix . 'wcfm_ie_cron_errorlog';
		$order_table_name = $wpdb->prefix . 'api_order';
		$pf_parts_db_version = '1.0.0';
		$charset_collate = $wpdb->get_charset_collate();

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {

			$sql = "CREATE TABLE $table_name (
			wc_ebay_multiseller_details_id INT NOT NULL AUTO_INCREMENT,
			ebay_user_id varChar(132),
			vendor_id INT,
			ebay_country INT,
			ebay_token TEXT,
			is_primary tinyint(2) NOT NULL DEFAULT '0',
			expire datetime,
			date_added datetime,
			date_modified datetime,
			PRIMARY KEY  (wc_ebay_multiseller_details_id)
		) $charset_collate;";

		dbDelta( $sql );
	//add_option( 'pf_parts_db_version', $pf_parts_db_version );
	}

	$sql = "CREATE TABLE IF NOT EXISTS $cat_table_name (
	`term_id` INT NOT NULL,
	`api_cat_id` INT NOT NULL
)$charset_collate;";
dbDelta( $sql );

$sql = "CREATE TABLE IF NOT EXISTS $order_table_name (
`order_id` INT NOT NULL,
`api_order_id` VARCHAR (264)
)$charset_collate;";
dbDelta( $sql );

$sql = "CREATE TABLE IF NOT EXISTS $request_table_name (
wcfm_ie_request_id INT NOT NULL AUTO_INCREMENT,
vendor_id INT,
type VARCHAR (64),
total_pages INT DEFAULT '0',
total_products INT DEFAULT '0',
done INT DEFAULT '0',
from_date DATETIME,
end_date DATETIME,
status INT DEFAULT '1',
date_added DATETIME DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY  (wcfm_ie_request_id)
)$charset_collate;";
dbDelta( $sql );

$sql = "CREATE TABLE IF NOT EXISTS $cron_request (
wcfm_ie_cron_request_id INT NOT NULL AUTO_INCREMENT,
vendor_id INT,
hook_name VARCHAR (264),
type VARCHAR (64),
from_date DATETIME,
expire DATETIME,
status INT DEFAULT '1',
date_added DATETIME DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY  (wcfm_ie_cron_request_id)
)$charset_collate;";
dbDelta( $sql );

$sql = "CREATE TABLE IF NOT EXISTS $modified_products (
wcfm_ie_modified_product_id INT NOT NULL AUTO_INCREMENT,
vendor_id INT,
ebay_user_id VARCHAR (64),
ebay_item_id VARCHAR (264),
date_modified DATETIME DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY  (wcfm_ie_modified_product_id)
)$charset_collate;";
dbDelta( $sql );

}

}
