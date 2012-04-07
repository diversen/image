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

moduleLoader::$referenceOptions = array ('type' => 'edit');

$link = moduleLoader::$referenceLink;
$headline = lang::translate('image_edit_image') . MENU_SUB_SEPARATOR_SEC . $link;
headline_message($headline);
template::setTitle(lang::translate('image_edit_image'));
$options = moduleLoader::getReferenceInfo();
image::setFileId($frag = 3);
image::init($options);
image::viewFileFormUpdate();
