<?php

/**
 * view file for adding files
 *
 * @package    image
 */
if (!session::checkAccessFromModuleIni('image_allow_edit')){
    return;
}

moduleloader::$referenceOptions = array ('type' => 'edit');
if (!moduleloader::includeRefrenceModule()){   
    moduleloader::$status['404'] = true;
    return;
}

// we now have a refrence module and a parent id wo work from.
$link = moduleloader::$referenceLink;

$headline = lang::translate('Delete all images') . MENU_SUB_SEPARATOR_SEC . $link;
html::headline($headline);

template::setTitle(lang::translate('Delete all images'));

$options = moduleloader::getReferenceInfo();

image::setFileId($frag = 3);
image::init($options);
image::viewFileFormDeleteAll();
