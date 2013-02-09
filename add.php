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
    moduleloader::$status['404'] = true;
    return;
}

// set headline and title
$headline = lang::translate('image_add_image') . MENU_SUB_SEPARATOR_SEC . moduleloader::$referenceLink;
html::headline($headline);
template::setTitle(lang::translate('image_add_image'));

// get options
$options = moduleloader::getReferenceInfo();

// set parent modules menu
layout::setMenuFromClassPath($options['reference']);

// display image module content
image::init($options);
image::viewFileFormInsert($options);
$rows = image::getAllFilesInfo($options);
echo image::displayFiles($rows, $options);
