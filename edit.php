<?php

if (!session::checkAccessControl('image_allow_edit')){
    return;
}

image::editController ();