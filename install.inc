<?php

/**
 * installer file for content module
 *
 * @package    files
 */

$_INSTALL['VERSION'] = 3.01;

$_INSTALL['PUBLIC_CLONE_URL'] = 'git://github.com/diversen/image.git';
$_INSTALL['PRIVATE_CLONE_URL'] = 'git@github.com:diversen/image.git';

$_INSTALL['MAIN_MENU_ITEM'] = array (
    'title' => lang::translate('Images', null, array ('no_translate' => true)),
    'url' => '/image/admin',
    'module_name' => 'content',
    'parent' => 0,
    'weight' => 2,
    'auth' => 'admin',
    'admin_only' => 1,
    'section' => 'content'
);
