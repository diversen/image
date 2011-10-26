<?php

/**
 * view file for adding files
 *
 * @package    image
 */
if (!session::checkAccessControl('image_allow_edit')){
    return;
}

if (!moduleLoader::includeRefrenceModule()){   
    moduleLoader::$status['404'] = true;
    return;
}

// we now have a refrence module and a parent id wo work from.
$link = moduleLoader::$referenceLink;

$headline = lang::translate('image_edit_image') . MENU_SUB_SEPARATOR_SEC . $link;
headline_message($headline);

template::setTitle(lang::translate('image_edit_image'));

$options = moduleLoader::getReferenceInfo();
//print_r($options);
image::setFileId($frag = 3);
$image = new image($options);
$image->viewFileFormUpdate();

