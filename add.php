<?php

/**
 * view file for adding files
 * @package    image
 */

if (!session::checkAccessFromModuleIni('image_allow_edit')){
    
    return;
}

moduleloader::$referenceOptions = array ('edit_link' => 'true');
if (!moduleloader::includeRefrenceModule()){   
    
    moduleloader::setStatus(404);
    return;
}


$options = moduleloader::getReferenceInfo();
//print_r($options);
$allow = config::getModuleIni('image_allow_edit');



// set headline and title
$headline = lang::translate('Add image') . MENU_SUB_SEPARATOR_SEC . moduleloader::$referenceLink;
html::headline($headline);
template::setTitle(lang::translate('Add image'));

// set parent modules menu
layout::setMenuFromClassPath($options['reference']);

// display image module content
image::init($options);
image::viewFileFormInsert($options);
$rows = image::getAllFilesInfo($options);
echo image::displayFiles($rows, $options);
