<?php

/**
 * view file for adding files
 *
 * @package    content
 */
if (!session::checkAccessControl('files_allow_edit')){
    return;
}

if (!include_module ($_GET['reference'])){
    moduleLoader::$status['404'] = true;
    session::setActionMessage("No such module: $_GET[reference]");
    return;
}

$class = moduleLoader::modulePathToClassName($_GET['reference']);
$link = $class::getLinkFromId($_GET['parent_id']);

$headline = lang::translate('image_add_image') . " :: " . $link;
headline_message($headline);

template::setTitle(lang::translate('image_add_image'));

$options = array (
    'parent_id' => $_GET['parent_id'],
    'reference' => $_GET['reference'],
    'redirect' => $_GET['return_url']);

$image = new image($options);
$image->viewFileFormInsert();
//$image->displayAllFiles();