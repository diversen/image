<?php

/**
 * view file for adding files
 *
 * @package    image
 */
if (!session::checkAccessControl('image_allow_edit')){
    return;
}

moduleLoader::$referenceOptions = array ('type' => 'edit');
if (!moduleLoader::includeRefrenceModule()){   
    moduleLoader::$status['404'] = true;
    return;
}

// we now have a refrence module and a parent id wo work from.
$link = moduleLoader::$referenceLink;

$headline = lang::translate('image_add_image') . MENU_SUB_SEPARATOR_SEC . $link;
headline_message($headline);

template::setTitle(lang::translate('image_add_image'));

$options = moduleLoader::getReferenceInfo();

image::init($options);
image::viewFileFormInsert($options);
$rows = image::getAllFilesInfo($options);
echo image::displayFiles($rows, $options);

