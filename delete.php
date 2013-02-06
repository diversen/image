<?php

/**
 * view file for adding files
 *
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

// we now have a refrence module and a parent id wo work from.
$link = moduleloader::$referenceLink;

$headline = lang::translate('image_delete_image') . MENU_SUB_SEPARATOR_SEC . $link;
headline_message($headline);

template::setTitle(lang::translate('image_delete_image'));

$options = moduleloader::getReferenceInfo();

image::setFileId($frag = 3);
image::init($options);
image::viewFileFormDelete();