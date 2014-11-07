<?php

/**
 * view file for adding files
 * @package    image
 */
if (!session::checkAccessFromModuleIni('image_allow_edit')){
    moduleloader::setStatus(403);
    echo lang::translate("Access denied");
    die();
}

$allow = config::getModuleIni('image_allow_edit');

// if allow is set to user - this module only allow user to edit his own images
if ($allow == 'user') {
    
    $table = moduleloader::moduleReferenceToTable($_GET['reference']);
    if (!user::ownID($table, $_GET['parent_id'], session::getUserId())) {
        moduleloader::setStatus(403);
        echo lang::translate("Access denied");
        die();
    }   
}

$options = array ();
$options['parent_id'] = $_GET['parent_id'];
$options['reference'] = $_GET['reference'];

// display image module content
image::init($options);
image::validateInsert();
if (!isset(image::$errors)) {
    $res = @image::insertFile();
    if ($res) {
        echo lang::translate('Image was added');
    } else {

        echo reset(image::$errors);
    }
} else {
    echo reset(image::$errors);
}

die();