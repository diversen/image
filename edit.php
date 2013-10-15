<?php

/**
 * view file for adding files
 *
 * @package    image
 */
if (!session::checkAccessFromModuleIni('image_allow_edit')){
    return;
}

moduleloader::$referenceOptions = array ('edit_link' => 'true');
if (!moduleloader::includeRefrenceModule()){   
    moduleloader::$status['404'] = true;
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

$link = moduleloader::$referenceLink;
$headline = lang::translate('Edit image') . MENU_SUB_SEPARATOR_SEC . $link;
html::headline($headline);
template::setTitle(lang::translate('Edit image'));

image::setFileId($frag = 3);

// set parent modules menu
layout::setMenuFromClassPath($options['reference']);
image::init($options);
image::viewFileFormUpdate();
