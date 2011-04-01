<?php

/**
 * controller file for doing downloads
 *
 * @package     module_system
 */

include_module('image');
$image = new image();
image::setFileId();
$file = $image->getFile();

header("Content-type: $file[mimetype]");
echo $file['file'];
die;
