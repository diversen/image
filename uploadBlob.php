<?php

namespace modules\image;

use diversen\conf;
use diversen\db;
use diversen\upload\blob;
use PDO;
use diversen\moduleloader;

class uploadBlob extends \modules\image\module  {
    /**
     * method for inserting a file into the database
     * (access control is cheched in controller file)
     *
     * @param array Array ( [name] => Angus_cattle_18.jpg [type] => image/jpeg [tmp_name] => /tmp/php5lPQZT [error] => 0 [size] => 52162 )
     * @return boolean true on success or false on failure
     */
    public function insertFileDirect ($file, $reference, $parent_id, $user_id) {
        
        // Load scale widths
        moduleloader::includeModule('image');
echo conf::getModuleIni('image_scale_width_thumb');
die;        
        $options = array();
        $options['maxsize'] = $this->maxsize;
        $options['allow_mime'] = $this->allowMime;
        
        // get med size
        $med_size = conf::getModuleIni('image_scale_width');
        
        // get fp - will also check for error in upload
        $fp = blob::getFP($file, $options);
        if (!$fp) {
            $this->errors = blob::$errors;
            return false;
        } 
        
        $values['file_org'] = $fp;
        
        // we got a valid file pointer checked for errors
        // now we use the tmp file when scaleing. Only
        // scale if an scaleWidth has been set. 
        
        $this->scaleImage(
                $file['tmp_name'], 
                $file['tmp_name'] . "-med", 
                $med_size);
        
        $fp_med = fopen($file['tmp_name'] . "-med", 'rb');
        $values['file'] = $fp_med;
        
        $this->scaleImage(
                $file['tmp_name'], 
                $file['tmp_name'] . "-thumb", 
                conf::getModuleIni('image_scale_width_thumb'));
        $fp_thumb = fopen($file['tmp_name'] . "-thumb", 'rb'); 
        
        $values['file_thumb'] = $fp_thumb;
        $values['title'] = $file['name'];
        $values['mimetype'] = $file['type'];
        $values['parent_id'] = $parent_id;
        $values['reference'] = $reference;
        $values['abstract'] = '';
        $values['user_id'] = $user_id;
        
        $bind = array(
            'file_org' => PDO::PARAM_LOB, 
            'file' => PDO::PARAM_LOB,
            'file_thumb' => PDO::PARAM_LOB,);
        
        $db = new db();
        $res = $db->insert($this->fileTable, $values, $bind);
        return $res;
    }  
}
