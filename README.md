# CKEditor Images Manager
CKEditor Images Manager is a plugin that allows you to upload images to your server easily and add to CKEditor. You can browse and manage your uploaded files without using a FTP Client - right in your browser.

## Installation
1. Extract the downloaded file into the CKEditor’s *plugins* folder. 
2. Then enable the plugin by changing or adding the extraPlugins line in your configuration (config.js):

### Defining Configuration In-Page
```javascript
CKEDITOR.replace( 'editor1', {
  extraPlugins: 'imagesmanager'
});
```

### Using the config.js File
```javascript
CKEDITOR.editorConfig = function( config ) {
  config.extraPlugins = 'imagesmanager';
};
```

Don't forget to set `CHMOD writable permission (0777)` to the upload folder on your server.


### How to use
Click image button on toolbar. Then click **Browse server**. A new window will open where you see all your uploaded images. To use the file click **Insert image button**. To upload a new image click **Upload image** button or just drag&drop file.
