# Salsify Product Sync for Magento2

---

## Overview

 Salsify is a leading cloud base PIM solution that can store and organize all your product data. We provide the tooling to map almost anything from Salsify into Magento 2, so that you can leverage Salsify’s powerful PIM features as your ‘source of truth’ for your catalog, while taking advantage of Magento’s best in class eCommerce capabilities.

## Purpose

 The Magento 2 Salsify Connector allows you to connect your Magento 2 store to your Salsify account in order to easily manage products across platforms in one place. Update and publish your products in Salsify and have them sync automatically to your Magento 2 storefront. You choose what Salsify Properties automatically pull into your Magento 2 store to update and what features to enable, such as product image galleries, or the ability to map configurable (base) products to simple (sellable) products.

# Magento Installation and Configuration

## Installation

There are two methods of installation:


1. Installation from a provided archive file
2. Install with composer

### Install from Archive


 1. Install the Magento 2 Salsify Connector Module from Archive.
	1. Unzip the module archive.
	1. Upload the folder to your Magento 2 directory on the web server.
	1. Run the commands in the Magento 2 directory via CLI:

```sh
php bin/magento module:enable Trellis_Salsify
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache flush
```

### From Composer

`todo`

## Configuration


Navigate to the Salsify Connector settings through your Magento 2 Admin Panel.
	1. Stores -> \[Settings\] Configuration -> \[Trellis\] Salsify

 3. Configure the Salsify Connector.



### General

 1. Enabled

    Enable or disable the complete functionality of the module.

 2. Allow Delete

    Allow the module to delete product records that no longer reside in your Salsify channel.

 3. Ignore Timestamp and Always Update

    Ignore the Salsify timestamp and update the products.

 4. API Base URL

    The URL used to access the Salsify API.

    **IMPORTANT**

    If your organization is part of a "multi-organization" - you will need to enter your API URL like so:

    `https://app.salsify.com/api/orgs/<your-organizataion-id>/`

    Replace `<your-organizataion-id>` with your organization ID. An example organization ID looks like: `s-69316d0b-37d1-9adb-b631-4fzf96f39193`. DO NOT FORGET THE TRAILING SLASH.

 5. API Key

    Your auth token (API Key) can be retrieved by going to Profile -> API Access -> Show API Key.

    The Salsify Magento 2 Connector uses a query parameter to pass your auth token: `https://app.salsify.com/api/v1/products/1234?access_token=<auth_token>`. In order to call any Salsify APIs, you must provide your authentication token (API Key). The auth token is specific to a user account.

 6. Product Feed Channel ID

    The Salsify Magento 2 connector uses channels to retrieve the latest product feed. A channel is configured to export your selected products and their attributes in JSON format.

    1. Set Up a Channel to Export your JSON

        1. Click "Product Selection" and choose your products.

   	    2. Go back and click "Product Feed".

        3. Set "Format" to JSON.

  	    4. Choose the columns you want to export (these are product attributes you have in Salsify).

        5. Go back and click _Publication Schedule_ - you can configure this channel to be published every night so that you are dealing with up-to-date data.

    2. Retrieve the Channel ID

        a) This ID will be used to target the channel for future requests. It can be found at the end of the URL when you click into the channel.

        b) https://app.salsify.com/app/channels/123456

 7. API Timeout

	Set the Timeout (in milliseconds) for the API calls. If the API receives no response in the given time, the APU will timeout.

 8. SSL Verify Peer

	Verify the authenticity of the peer's certificate. When negotiating an SSL connection, the server sends a certificate indicating its identity. The Salsify Connector will verify whether the certificate is authentic.

 9. Sync Action

    The Sync Action button will trigger a manual sync from your Salsify Channel into Magento 2. Always remember to re-publish your channel on the Salsify platform to get the latest updates.

### Webhooks

 1. Enabled

    Enable or disable the functionality of the webhooks. Please see section "Usage" on setting up automatic webhooks.

 2. Client ID

    The client ID is used to identify the request as actually coming from Salsify. This ID is user-made. It can be any string of characters, and any length. For security purposes, we recommend a string with at least 12 characters.

 3. Cron Expression

    Cron expression for the execution to check if updates from Salsify have been recorded. For examples of cron expressions and help building your own, please visit [crontab.guru](https://crontab.guru/)

    Please read more about Webhooks in the Usage section below.

### Configurable Products

 1. Enabled

    Enable or disable the functionality of configurable products.

 2. Attributes Field

	A comma-separated list of attributes used to construct the configurable products. <br>
	In order to map configurable (base) products to simple (sellable) products from Salsify to Magento 2, we need to supply Salsify products with a user created property to do so. In our example, we've created a property `magento_configurable_attributes` with a value `parent_color` in Salsify.  Then in Magento, we use Property Mapping to map the attribute:

    ```json
    {
       "Parent Color":"parent_color"
    }
    ```

    In the Salsify payload, Sellable (simple) products have a property `parent_id` that lets us know what Base (configurable) product it belongs to. Base (configurable) products do not contain this property. In our example, we are using the Sellable (simple) products color property to create different (simple) products in Magento.

### Bundled Products

 1. Enabled

    Enable or disable the functionality of bundled products.

 2. Attributes Field

    Specify the Salsify Property in which the connector will look for a comma separated list of product skus to create bundled products from.

### Grouped Products

 1. Enabled

    Enable or disable the functionality of configurable products.

 2. Attributes Field

    Specify the Salsify Property in which the connector will look for a comma separated list of product skus to create grouped products from.

### Downloadable Products

 1. Enabled

    Enable or disable the functionality of configurable products.

 2. Downloadable Products Details Field

	Specify the Salsify Property in which the connector will look for to determine the details of the Downloadable Product.

	A json object is required to set up and map downloadable details. For each option, there are required fields that also need to be set. Here is an example json object that would map the downloadable title, link type, and link url:

	```json
	{"title":"Title", "type":"url", "url_link":"http://example.com/download"}
    ```

    `title`, `url`, and `url_link` are required for downloadable products.

 3. Downloadable Product Sample Field

    UNDER DEVELOPMENT

	Specify the Salsify Property in which the connector will look for to determine if the product has sample links.

### Media Images

 1. Digital Asset Feed Enabled

    Enable or disable the Top Level Digital Asset Feed.

    The Salsify Product Feed from your channel may contain a broken-out payload of the digital assets your products include. If this is the case, we use this option to tell the connector to look in this broken-out payload for your products digital assets.

 2. Product-Level digital Assets Enabled

    The Salsify Product Feed from your channel may contain an included payload of the digital assets your products include. If this is the case, we use this option to tell the connector to look in the included product payload for your products digital assets.

 3. Automatically Enable New Images

    Automatically enable new images to appear on product pages.

 4. Image Mapping Enabled

	Enable or disable the functionality of Image Mapping.

	The Salsify Connector allows you to map images from Salsify to their products in Magento. In order to map images, we need to supply a mapped Salsify property to Magento attributes.

 5. Image Tag Mapping

    Much like the Field Mapping feature, the Salsify Connector allows you to map images from Salsify to their products in Magento. To do so, provide a `json` object to map your Salsify Property to Magento attributes.

    ```json
    {
        "Main Image":["image","thumbnail"]
    }
    ```

 6. Media Gallery Enabled

    Enable or disable the functionality of the Image Gallery.

    Media Gallery functionality allows you to assign multiple images from a Salsify Property into the product Media Gallery. The connector will download the images from this Salsify Property and map them accordingly into the Product Media Gallery.

 7. Media Gallery

    Input the Salsify Property which holds additional media images for your product.

 8. Media Gallery Download Path

    Directory where the images will be downloaded. (inside pub/media/import)

 9. Enable Video

    To enable video functionality, you must first enter your YouTube API key in Stores > Configuration > Catalog [Catalog] > Product Video.

    Enable or disable the functionality of product videos.

10. Video Property

    A Salsify Property that holds the json object for product video details.

    ```
    {
    	"1": {
    		"video_title": "Product Video",
    		"video_url": "https://www.youtube.com/watch?v=7CU7JrHwgb4",
    		"video_description": "Product Video Sample",
    		"video_mediaattribute": "base,image,thumbnail"
    	}
    }
    ```

    `video_url` is required.

    `video_title` is required.

    `video_description` is optional.

    `video_mediaattribute` is optional. Default is null. Options: base, image, thumbnail

### Field Mapping

 When a Salsify client doesn’t name product properties in a "Magento" friendly way, we need to map Salsify Properties to Magento Attributes.

####  IMPORTANT

 Salsify referrers to product attributes as "properties". When working with Magento, these are "attributes".


 Salsify  | Magento
 -------- | ------
 "property" | "attribute"

####  IMPORTANT

 Product attributes that you wish to map need to be created in Magento prior to syncing.

####  IMPORTANT

 Product attributes that you wish to use to map configurable products to child products need to be created in Magento prior to syncing.

 1. Property Mapping

    The value of the setting expects a JSON object. For example, in order to map "Vendor SKU" from Salsify to the Magento "sku" product attribute, our `Property Mapping` would look like this:

    ```json
    {
      "Vendor SKU":"sku",
      "Item Name":"name"
    }
    ```

    We can also pass an array to set the value in multiple attributes:

    ```json
    {
      "Vendor SKU":["sku", "url_key"],
      "Item Name":"name"
    }
    ```

    In the above example, our "Vendor SKU" will be used to map our product to the Magento "sku" attribute, as well as the "url_key" Magento attribute.

    For instance, if our "Vendor SKU" is TEST-0001 in Salsify, our mapping will create (or update) a Magento product with a "sku" of TEST-0001 and a "url_key" of `/test-0001.html`.

### Custom Options

 1. Enabled

    Enable or disable the functionality of custom options.

 2. Attributes Field

    Specify the Salsify Property in which the connector will look for a json object to create custom options from.

    A json object is required to set up and map a products custom options. For each option, there are required fields that also need to be set. Here is an example json object that would map one custom option "Color" with three options "Red", "White", and "Blue".

    ```json
    {
       	"1": {
       		"sort_order": 1,
       		"title": "Color",
       		"price_type": "fixed",
       		"price": "",
       		"type": "drop_down",
       		"is_require": 0,
       		"is_default":1,
       		"values": [{
       				"record_id": 0,
       				"title": "Red",
       				"price": 10,
       				"price_type": "fixed",
       				"sort_order": 1,
       				"is_delete": 0
       			}, {
       				"record_id": 1,
       				"title": "White",
       				"price": 10,
       				"price_type": "fixed",
       				"sort_order": 1,
       				"is_delete": 0
       			}, {
       				"record_id": 2,
       				"title": "Blue",
       				"price": 10,
       				"price_type": "fixed",
       				"sort_order": 1,
       				"is_delete": 0
       			}
       		]
       	}
    }
    ```

### Product Relation Settings

 1. Enabled

    Enable or disable the functionality of related products.

 2. Property

    Specify the Salsify Property in which the connector will look for a comma separated list of product skus to create related products from.

    --> **ATTACH A PROPER IMAGE** <--

 3. Product Crosssell Enabled

    Enable or disable the functionality of crosssell products.

    --> **ATTACH A PROPER IMAGE** <--

 4. Products Crosssell Property

    Specify the Salsify Property in which the connector will look for a comma separated list of product skus to create crosssell products from.

 5. Product Upsells Enabled

     Enable or disable the functionality of upsell products.

 6. Product Upsell Property

     Specify the Salsify Property in which the connector will look for a comma separated list of product skus to create upsell products from.

     --> **ATTACH A PROPER IMAGE** <--

### Attribute Set Settings

 1. Attribute Set Mapping Enabled

    Enable or disable the Attribute Set Mapping feature.

 2. Attribute Set Property

    The Salsify Property you’ve defined that holds the name of the Magento Attribute Set you wish to map the product to.

    e.g. If you have a Magento Attribute Set "Accessories", you would create a Salsify Property, for example "Attribute Set", with a value "Accessories". When the product is created, it will be placed in the "Accessories" Attribute Set.

    Please note, Magento Attribute Sets that you wish to import into must exist before importing can take place. The Trellis Salsify Connector cannot create Magento Attribute Sets on-the-fly.

    attribute_set_code | Bag
    --- | ---

### Category Import Settings

 1. Category Mapping and Generation

    Enable or disable the Category Mapping and Generation feature.

    Category Mapping and Generation allows the mapping or creation of categories for your products on-th-fly.

 2. Category Parent

    When generating categories from Salsify Properties, specify the Magento 2 Root Category upon which ALL categories from Salsify will be created under.

 3. Category Field

    This field allows you to input a Salsify Property that you use for categories. Categories in Salsify can be (and should be) nested. The Magento 2 Salsify Connector will recognize these nested categories and place the products in their respective category, or create the categories on-the-fly.

 4. Category Type

    Currently, the Trellis Salsify Connector only supports "Simple String" type categories.

 5. Category String Delimiter

    Enter the delimiter for category strings. This CAN NOT be a comma.

    An example category string in Salsify would look like: `Grandparent > Parent > Child`, with the delimiter set as `>` in the Magento configuration field.

### Default Values

 1. Default Value Mapping Enabled

    Enable or disable the Default Value Mapping feature.

 2. Default Value Mapping

   The value of the setting expects a JSON object.

   ```json
   {
       "status": true,
       "stock_data": {
           "is_in_stock": true,
           "manage_stock": false,
           "qty":9999,
           "use_config_manage_stock": false
       },
       "visibility": 4
   }
   ```

### RabbitMQ

 RabbitMQ is a lightweight message and queueing application. It allows a reduced load and delivery of a Salsify sync.

 1. Enabled

    Enable or disable the RabbitMQ functionality.

 2. Host

    The host where RabbitMQ is installed.

 3. Port

    The port RabbitMQ should be listening on.

 4. User

    The RabbitMQ user.

 5. Password

    The password of the defined RabbitMQ user.

 6. VHost

    The virtual host for your RabbitMQ instance. More information about RabbitMQ Virtual Hosts can be found [here](https://www.rabbitmq.com/vhosts.html).

 7. Number of Products

    The number of products imported from RabbitMQ per cron execution.

 8. Cron Expression

    Cron expression for the execution of the process of message consumption. For examples of cron expressions and help building your own, please visit [crontab.guru](https://crontab.guru/)


### Debugging

 1. Salsify Connector Version Number

    Displays the currently installed version of the Trellis Salsify Connector.

 2. Download Log File

    If you ever have any problems with the Magento 2 Salsify Connector, a button is provided to download the latest log files generated by the connector. You can use this file to debug any problems the connector may be having.

## Sync Methods

## Manual Sync

 **Button Sync**

 Arguably the easiest way to sync your products between the Salsify & Magento 2 platforms, the Connector offers syncing your products at the press of a button. Located in the Magento 2 Admin Panel: Stores → Configuration → [Tab] **Trellis** → **Salsify** -> (Section) General → **Salsify Sync**.

 2. Command Line Interface

    Another way to sync your products between the Salsify & Magento 2 platforms is by use of a built-in console command. On your server, navigate via console to your Magento 2 directory and execute the following command:

    ```bash
    php bin/magento salsify:sync
    ````

## Automatically

 When a product is updated on the Salsify platform, it is capable of setting a flag on your Magento 2 instance via webhooks, then updating those products in Magento 2 via cron on a schedule.

 1. Webhooks

    To use webhooks, they need to be enabled on your Magento 2 platform as described above, and enabled on your Salsify platform.

    Magento 2: Stores → Configuration → [Tab] Trellis → Salsify → Webhooks

    1. Enable Webhooks

    2. Create a Client ID

    Salsify: More → Channels → (Your Channel) → Notifications → Call Webhook: Successful Publication

    The build of the URL is important: https://your-store.com/rest/all/V1/salsify/update/**&lt;Client ID&gt;**

    The Client ID you’ve created in the Magento 2 Salsify

    Connector admin settings panel needs to match the one at the end of the URL in order for webhooks to successfully communicate and verify the request.

    The route rest/all/V1/salsify/update/ sets an internal flag that triggers the updating of products.

 2. Cron

    The webhook described above will set a timestamped flag when called upon. The Magento 2 cron will pick up that flag and run the product update. After a successful update, the flag will be removed in anticipation of the next webhook update. This cron is set to run every 15 minutes by default to ensure the system is not overloaded with requests from Salsify.


# Roadmap
* Implement Composer

# Version History

version | Revision Date | Changelog
--------| ------------- | ---------
1.4.12  | 20190905      | Fix product relation links.
1.4.11  | 20190903      | Fix multi-select attribute save.
&nbsp;  | &nbsp;        | Admin panel QoL improvements.
&nbsp;  | &nbsp;        | Fix system select attribute mapping.
&nbsp;  | &nbsp;        | Fix multi-select attribute mapping.
&nbsp;  | &nbsp;        | Fix array issues.
1.4.6   | 20190829      | Allow changing simple to configurable product.
&nbsp;  | &nbsp;        | Fix saleable product property inheritance.
1.4.4   | 20190826      | Decouple webhook and manual executions.
&nbsp;  | &nbsp;        | Fix space in Update.php
&nbsp;  | &nbsp;        | Update README.md
1.4.1   | 20190802      | Remove $digitalAssetExportUrl parameter.
1.4.0   | 20190801      | MEQP2 fixes.
&nbsp;  | &nbsp;        | Fix Webhooks to digest POST events.
&nbsp;  | &nbsp;        | Cleanup Webhook methods.
&nbsp;  | &nbsp;        | Stop Webhook cron from running if it is disabled.
1.3.4   | 20190717      | Add proxy to avoid area code not set.
&nbsp;  | &nbsp;        | Add message for null payloads.
&nbsp;  | &nbsp;        | Allow webhook cron to be declared by the client.
&nbsp;  | &nbsp;        | Turn comments into tooltips.
1.3.0   | 20190702      | Automatic downloadable packages.
1.2.9   | 20190625      | RabbitMQ connection fix.
1.2.8   | 20190618      | Fix attributes global mapping.
&nbsp;  | 20190617      | Fix attributes global mapping.
&nbsp;  | 20190613      | Fix ordering.
&nbsp;  | &nbsp;        | Fix ordering.
&nbsp;  | &nbsp;        | Fix image attribute update.
&nbsp;  | 20190618      | Improved category sync.
&nbsp;  | &nbsp;        | Fix trim.
&nbsp;  | 20190612      | Improved category mapping.
1.2.0   | 20190529      | Fix conflict.
&nbsp;  | &nbsp;        | Implement RabbitMQ.
&nbsp;  | &nbsp;        | Implement RabbitMQ.
&nbsp;  | &nbsp;        | Implement RabbitMQ.
&nbsp;  | &nbsp;        | Implement RabbitMQ.
&nbsp;  | &nbsp;        | Implement RabbitMQ.
&nbsp;  | &nbsp;        | Refactor.
&nbsp;  | &nbsp;        | Complete CS.
&nbsp;  | &nbsp;        | Fix undefined variable.
1.1.3   | 20190524      | Fix category check. Fix chance of duplicate video.
1.1.2   | 20190523      | Fix category mapping issues.
&nbsp;  | &nbsp;        | Fix duplicate image downloads.
1.1.0   | &nbsp;        | Add product video functionality.
1.0.17  | 20199512      | Better error handling on media uploads.
&nbsp;  | &nbsp;        | Validate media file names.
&nbsp;  | &nbsp;        | Better error handling on proudct updates.
&nbsp;  | &nbsp;        | Cleaner logging.
&nbsp;  | &nbsp;        | Convert to LF.
&nbsp;  | 20190508      | Fix undefined variable.
1.0.11  | &nbsp;        | Improve logging accuracy.
&nbsp;  | &nbsp;        | Remove unnecessary logging.
&nbsp;  | &nbsp;        | PHPDoc Block generation.
&nbsp;  | &nbsp;        | README.md additions, fixes.
&nbsp;  | &nbsp;        | Comment attribute logging.
&nbsp;  | 20190507      | Fix multi-select values being set.
&nbsp;  | &nbsp;        | Fix issue with catching the incorrect skus.
1.0.4   | 20190506      | Add README.md to module files.
&nbsp;  | &nbsp;        | Fix label setting for attributes.
&nbsp;  | &nbsp;        | Check all products on update for metadata.
&nbsp;  | 20190502      | Log Magento Edition, Version
1.0.0   | 20190304      | Initial Release
