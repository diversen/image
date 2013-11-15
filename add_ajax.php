<?php

/**
 * org content


if (!session::checkAccessFromModuleIni('image_allow_edit')){
    return;
}

template::render('basic');

if (!moduleloader::includeRefrenceModule()){   
    moduleloader::$status['404'] = true;
    return;
}

$options = moduleloader::getReferenceInfo();


$message = session::getActionMessage();
if ($message) {
    html::confirm($message);
}
image::init($options);
image::viewIframeFileFormInsert($options);
$rows = image::getAllFilesInfo($options);
echo image::displayFiles($rows, $options);
//die;

*/
/**
 * view file for adding files
 *
 * @package    image
 */
if (!session::checkAccessFromModuleIni('image_allow_edit')){
    return;
}

template::render('basic');

if (!moduleloader::includeRefrenceModule()){   
    moduleloader::$status['404'] = true;
    return;
}



$image = new image();
$image->viewFileForm('insert');
//print_r($options);
//image::init($options);
//image::viewIframeFileFormInsert($options);
//$rows = image::getAllFilesInfo($options);
//echo image::displayFiles($rows, $options);


