Basic News Submission
===================

With this bundle you can create a News from frontend. You make a module type `News Submission` and select *editable* fields.

By default some fields from `tl_news` are already made editiable, but you can add more fields as shown below.


## Adding more fields

You can add more fields from `tl_news` to editable list by adding `['eval']['feEditable'] = true`

```php
/* mark these standard fields as editable */
foreach (array('headline', 'teaser', 'location', 'url', 'singleSRC', 'enclosure') as $key) {
    $GLOBALS['TL_DCA']['tl_news']['fields'][$key]['eval']['feEditable'] = true;
}
```


## Notification Tokens

By submiting an news you can send a notification of type `News Submit`.

All news fields from the perticlur news (`tl_news`) are avaliable as notification simple token
```php
##news_*##
```
Also for file upload, then link to the file is available as.
```php
##news_{uploadFieldName}_path##
```

All news fields from the Module `News Submission` are also available inside notification.

```php
##newssubmit_mod_*##
```

If there is a logged in frontend user, then all member fields are also available inside notification.

```php
##member_*##
```
If there is a user is guest user then flowing infromation available inside notification.
```php
##GuestCompany##, 
##GuestTitle##, 
##GuestFirstname##, 
##GuestLastname##, 
##GuestEmail##
````

## Configuration
```php
//inside your module config.php

//If there are uploads then create a destination subfolder automatically inside the base folder.
//Subfolder name is YYYYMMDD-HHMM-NewsID. Set to false if you like to have all files inside the base folder
$GLOBALS['BS_NewsSubmit']['BS_CUSTOM_FOLDER'] = true;

//If you prefer to have another naming for the subfolder, then define a clouser function like example give below
$GLOBALS['BS_NewsSubmit']['BS_CUSTOM_FOLDER_FUNCTION'] = null;
```

## Upload destination

You define a base destination location for the uploads from module. For each News submission with upload file, a subfolder is created automatically within the base folder.

The naming of this subfolder is `Date-Time-NewsID` with format `YYYYMMDD-HHMM-ID`. You can change to your need by defining a clouser function inside config array as below.

```php

/**
 * Example folder name callback
 */
$GLOBALS['BS_NewsSubmit']['BS_CUSTOM_FOLDER_FUNCTION'] =  function ($obj, $basePath) {

    //You can add any logic here for folder name.
    $newFolder = rand(0, 100);

    $objFolder = new \Contao\Folder($basePath . '/' . $newFolder);

    if (($uuid = $objFolder->getModel()->uuid) == null) {
        //We fall here if the folder is excluded from the DBAFS
        $fileModel = \Contao\Dbafs::addResource($objFolder->path);
        $uuid = $fileModel->row()['uuid'];
    }

    return $uuid;
};
```

## More custom fields

You can add more custom fields from your bundle by adding more DCA flields to `$GLOBALS['TL_DCA']['tl_news']['fields']`. These custom fields must have `eval` with `'feEditable' => true`.

