<?php

use diversen\conf;
use diversen\db;
//use diversen\moduleloader;

use modules\image\module as image;
//moduleloader::includeModule('image');

class image_ext extends image {
    /**
     * method for inserting a file into the database
     * (access control is cheched in controller file)
     *
     * @return boolean true on success or false on failure
     */
    public static function insertFile ($values = null) {
        $db = new db();

        //$_POST = html::specialDecode($values);
        //$options['filename'] = 'file';
        $options['maxsize'] = self::$maxsize;
        $options['allow_mime'] = self::$allowMime;

        $tmp_file = sys_get_temp_dir() . "/" . uniqid();
        file_put_contents($tmp_file, $values['file_org']);
         
        self::scaleImage(
                    $tmp_file, 
                    $tmp_file . "-med", 
                    conf::getModuleIni('image_scale_width'));    
        $values['file'] = file_get_contents($tmp_file . "-med");
        
        self::scaleImage(
                    $tmp_file, 
                    $tmp_file . "-thumb", 
                    conf::getModuleIni('image_scale_width_thumb'));
        $values['file_thumb'] = file_get_contents($tmp_file . "-thumb");

        unlink($tmp_file); 
        unlink($tmp_file . "-med"); 
        unlink($tmp_file . "-thumb");
        
        $res = $db->insert(self::$fileTable, $values);
        return $res;
    }
    
}