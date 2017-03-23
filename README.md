About
=====

This small library (not a bundle!) simplifies uploading files to an external resource via
an API. Since version 1.2, this library depends on Symfony.

This library does not do anything by itself, it is designed to be extended by other
bundles or libraries.

Installation
============

Nothing to install here, really.

Usage
=====

In Symfony projects
-------------------

First, you need to create a concrete service implementation of ```AbstractUploader``` class.
This service needs to have a single method called ```upload()``` that does not accept
arguments and by convention should return ```$this```.

Here are the properties that can be accessed from inside that method:

- ```$this->file``` - ```Symfony\Component\HttpFoundation\File\File``` object acting as
a handler for original file
- ```$this->uploadURL``` - full path to the remote destination folder without trailing slash
- ```$this->filename``` - the name that will be given for your newly uploaded file -
see name changers

If you register this service inside Symfony service container, you will need to install
VKRSettingsBundle and add it as an argument to ```services.yml```:

```
my_uploader:
    class: AppBundle\MyUploader
    arguments:
        - "@vkr_settings.settings_retriever"
```

Then, you will need to create three settings either as parameters or inside your DB:
- ```allowed_upload_types``` - an array of MIME types
- ```allowed_upload_size``` - max size in bytes that can be uploaded
- a setting with arbitrary name with full URL to remote destination folder, with or without
trailing slash

See VKRSettingsBundle manual on how settings can be defined.

Here is how you access this service from your controller:

```
$uploader->setUploadDir('my_upload_dir_setting_name');
$file = new Symfony\Component\HttpFoundation\File\File('path/to/file/filename');
$uploader->setFile($file);
$uploader->upload()->checkIfSuccessful();
```

Here ```checkIfSuccessful()``` checks if the uploaded file exists, has same size and MIME
type as the original file and throws exception on error.

If you want to disable checks for size and MIME type, you can call:

```
$uploader->setFile($file, null, false);
```

Name changers
-------------

The second argument to ```setFile()``` is a name changer object. It defines what name will
the new file have. If no name changer is present, the new file will have the same name
as the original. Name changers must implement ```NameChangerInterface```.

Suppose that you want all files to be renamed with current Unix timestamps, in this case
your name changer will look like this:

```
class MyNameChanger implements VKR\SymfonyWebUploader\Interfaces\NameChangerInterface
    public function changeName($originalFilename)
    {
        return time();
    }

    public function setParameters(array $parameters)
    {
    }
```

Then in your controller:

```
$nameChanger = new MyNameChanger();
$uploader->setFile($file, $nameChanger);
```

You do not need to create a name changer if your file was just uploaded by a client
and you want to keep the client filename in this case the default new name will correspond
to the client filename, not to the PHP temporary name.

Outside of Symfony
------------------

Things are not that different outside of Symfony, however you cannot use VKRSettingsBundle
and must pass your settings as an array:

```
$settings = [
    'allowed_upload_size' => 10000,
    'allowed_upload_types' => ['image/jpeg, 'image/png'],
    'upload_dir' => 'http://my-upload-domain.com/upload-folder/',
];
$uploader = new VKR\SymfonyWebUploader\Uploader(null, $settings);
```

Also, if you are forwarding a file that was just uploaded from a form, you need to
initialize it manually:

```
$file = new Symfony\Component\HttpFoundation\File\UploadedFile($_FILES['file']['tmp_name'], $_FILES['file']['name']);
$uploader->setFile($file);
```
