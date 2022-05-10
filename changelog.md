# Changelog

------

## **2.0.8**

* **(SAL-142)** - Categories can now be specified with names and missing categories are created. Categories specified by IDs are still supported, and the column name remains the same in salsify: category_ids.  If names are used, the hierarchy of names starting from the base category and separated by '/' must be specified; multiple categories are separated by a pipe "|".  This will also automatically create any missing categories.  Example:  `Default Category/Clothing/T-Shirts|Default Category/Clothing/Dress Shirts`
* **(SAL-132)** - Refactored the way that payloads are stored and queued up for CRON execution.  Payloads are stored for processing on magento and CRON will execute payloads for processing individually and in the order they were received and stored  
* **(SAL-134)** - Admin Grid created to view the payloads stored in Magento.

## **2.0.7**

* **(SAL-128)** -- Text swatches can include misc characters like quote and double-quote
* **(SAL-)** -- Updated version information to get package version and not setup version

## **2.0.6**

* **(SAL-126)** -- Resolve issues with media gallery causing long pauses during save process
* **(SAL-97)** -- Added salsify_error.log with additional error catching and logging.  Known issue: trellis_salsify.log is still catching all log output; salsify_error.log only catches critical and errors.  Also, resolved issue with productLinks class (unknown issue) and added error logging. 
* **(SAL-128)** -- Added support for text swatches; For attributes configured within magento as text swatches, you can optionally add the swatch label.  For example, "size" attribute option can be '<optiontext>, <swatchtext>' : Small, S  
* **(SAL-118)** -- Architectural Improvements; Added functionality for pre-processing and pre-handling of data to optimize and report issues;
* **(SAL-123)** -- Product Relations (upsell, cross-sell, related) fixed.  Columns in salsify: "related_skus", "crossell_skus", "upsell_skus"; Note: products in different feeds that are not yet created cannot have links made.
* **(SAL-113)** -- Bugfix - product and media object errors: Integrity Violation Issues 


## **2.0.5**

* **(SAL-111)** -- Sync preformance improvements.  Compare salsify assets by using md5_file. If asset coming from Salsify is an array, then use the hash contained in the array to compare.
* **(SAL-112)** -- Alt tag for gallery images. Add alt tag functionality to images added to gallery

----

## **2.0.4**

* **(SAL-122)** -- Multiple publishes.  This will double-check at the end of the process make sure all payloads have completed.  If there are existing payloads that have not processed it will set the magento flag so when the next time the cron runs it will pick up the payload and process it.


----

## **2.0.3**

* **(SAL-85)** -- Swatch Image feature request.  Added feature - Visual Swatch imports;  Attributes that are configured as visual swatches in Magento, will allow for the fields to include a URL or hex code, separated by comma.  Example: Black, #000000  
* **(SAL-115)** -- Readiness Report Product Import - Continue on Error. Added exception handling and logging of exception messages to the salsify log
* **(SAL-120)** -- Issues with attribute option of "0" is seen as false and not the value "0". Fixed - Accepts Attribute Value options to include the number zero (0) ...
