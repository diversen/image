<?php

/**
 * view file for adding files
 *
 * @package    content
 */
if (!session::checkAccessControl('image_allow_edit')){
    return;
}

image::addController ();
