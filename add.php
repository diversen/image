<?php

/**
 * view file for adding files
 *
 * @package    image
 */
if (!session::checkAccessControl('image_allow_edit')){
    return;
}

moduleLoader::$referenceOptions = array ('edit_link' => 'true');
if (!moduleLoader::includeRefrenceModule()){   
    moduleLoader::$status['404'] = true;
    return;
}



$headline = lang::translate('image_add_image') . MENU_SUB_SEPARATOR_SEC . moduleLoader::$referenceLink;
headline_message($headline);

template::setTitle(lang::translate('image_add_image'));
$options = moduleLoader::getReferenceInfo();

layout::setMenuFromClassPath($options['reference']);

image::init($options);
image::viewFileFormInsert($options);
$rows = image::getAllFilesInfo($options);
echo image::displayFiles($rows, $options);
