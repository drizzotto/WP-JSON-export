# Post JSONer

This plugin aims to export content to static JSON files. 
Through the WordPress admin section, it allows to configure export path, S3 buckets, 
and what post types to be exported and change their name. 
In the [Configuration](#configuration) section we will go in depth in those settings, 
and WordPress constants required.

Another option you could pick is the export mapper, that will be covered in the  [Customizations](#customizations)
section, and will also explains how to create and add customs mappers.  

This plugin supports Multi-site and Multi-language. In the case of languages and translations, 
currently it only supports through [WPML](https://wpml.org/) plugin.

The exported data can be uploaded to S3.

## Configuration

There are two ways to configure this plugin, with [Constants](#constants) and through the [admin page](#wp-options).
The first ones are for sensitive data that you may want set through environment variables, for example S3 credentials.

In the admin section, you can set the active WordPress environment and environment related settings like S3's bucket.

There are three supported environments:
* QA: it's used for development and testing purpose.
* UAT: this is a pre-production stage, usually mimic production infrastructure. 
* PROD: the Production one.


### CONSTANTS

All of them would be defined `wp-config.php` file to be available across the whole blog.

The first one is for active environment, by default *QA*. This one can be overridden in the admin page.
```php
define('WP_SITE_ENV', getenv('WP_SITE_ENV') ?? 'QA');
```

The S3 credentials may be set here too. In case of missing any of the constants for the active environment,
the upload feature will be automatically disabled.

The format for this set of constants is:

`S3_UPLOADS_<ENVIRONMENT>_KEY - S3_UPLOADS_<ENVIRONMENT>_SECRET - S3_UPLOADS_<ENVIRONMENT>_REGION`

Then for QA it's:
```php
define('S3_UPLOADS_QA_KEY', getenv('S3_UPLOADS_QA_KEY') ?? '');
define('S3_UPLOADS_QA_SECRET', getenv('S3_UPLOADS_QA_SECRET') ?? '');
define('S3_UPLOADS_QA_REGION', getenv('S3_UPLOADS_QA_REGION') ?? '');
```

For UAT:
```php
define('S3_UPLOADS_UAT_KEY', getenv('S3_UPLOADS_UAT_KEY') ?? '');
define('S3_UPLOADS_UAT_SECRET', getenv('S3_UPLOADS_UAT_SECRET') ?? '');
define('S3_UPLOADS_UAT_REGION', getenv('S3_UPLOADS_UAT_REGION') ?? '');

```

And for PROD:
```php
define('S3_UPLOADS_PROD_KEY', getenv('S3_UPLOADS_PROD_KEY') ?? '');
define('S3_UPLOADS_PROD_SECRET', getenv('S3_UPLOADS_PROD_SECRET') ?? '');
define('S3_UPLOADS_PROD_REGION', getenv('S3_UPLOADS_PROD_REGION') ?? '');
```

Last but not least, through constants you can define custom filename for error logging output,
by default:
```php
define('DEBUG_FILE','/var/log/wp-error.log');
```

### Settings Page

In this section you can set where the configuration for export format is save, by default, it's inside the plugin's directory
`wp-content/plugins/post-jsoner/config`

The export path, is where the data will be saved. It used to be inside `uploads` folder, but could be changed to any directory
that has the appropriate permissions.

![Posts JSONer Settings!](documents/Posts_JSONer_Settings.png "Posts JSONer Settings")

In the **Mapper** dropdown, you can select between different custom output mappings saved in the __**config path**__ as a directory
that contains `.json` files that establish the relationship between post object and the output layout.

In the [Customizations](#customizations) section, will explain how to create your own maps.

This plugin comes with a default one with the structure below:

```json
{
  "post": "post.*",
  "customs": "customs.*"
}
```

**Current Site Environment**, is self-explanatory. It allows you to establish which environment you're running on.

The **S3 Settings**, allows you to set the bucket and the path where the exported data will be uploaded, in case S3 is enabled for current environment.
As you can see in the image above, the section highlighted in green match the current site environment as a visual hint.

Also, if the constants for S3 discussed in the previous section are not set for current environment, the S3 will be disabled. 

Finally, for each _post type_, like _post_, _page_, etc., in a default WordPress installation, as well as custom post types defined,
you can choose what to export and what name will have the file generated.

## Export

Once you've completed the configuration, you can choose between __Full Export__, that will loop through all sites,
or you can select which you want to export, e.g. a specific country you have made changes.

![Posts JSONer Export!](documents/Posts_JSONer_Export.png "Posts JSONer Export")

## Customizations