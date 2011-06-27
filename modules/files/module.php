<?php

$autoload = array(
    'filesController'=>'controllers/filesController.php',
    'fileModel'=>'models/fileModel.php',
    'fileFolderModel'=>'models/fileFolderModel.php',
);

kanon::getModelStorage()
        ->registerCollection('fileModel', 'site_file')
        ->registerCollection('fileFolderModel', 'site_file_folder')
;
