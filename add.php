<?php

/**
 * view file for adding files
 * @package    image
 */
if (!session::checkAccessControl('image_allow_edit')){
    return;
}



moduleloader::$referenceOptions = array ('edit_link' => 'true');
if (!moduleloader::includeRefrenceModule()){   
    moduleloader::setStatus(404);
    return;
}

$options = moduleloader::getReferenceInfo();
$allow = config::getModuleIni('image_allow_edit');

// if allow is set to user - this module only allow user to edit his own images
if ($allow == 'user') {
    $table = moduleloader::moduleeReferenceToTable($options['reference']);
    if (!user::ownID($table, $options['parent_id'], session::getUserId())) {
        moduleloader::setStatus(403);
        return;
    }   
}




// set headline and title
$headline = lang::translate('image_add_image') . MENU_SUB_SEPARATOR_SEC . moduleloader::$referenceLink;
html::headline($headline);
template::setTitle(lang::translate('image_add_image'));

// set parent modules menu
layout::setMenuFromClassPath($options['reference']);

// display image module content
image::init($options);
image::viewFileFormInsert($options);
$rows = image::getAllFilesInfo($options);
echo image::displayFiles($rows, $options);
