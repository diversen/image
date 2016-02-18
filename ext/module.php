<?php

namespace modules\image\ext;

use diversen\conf;
use diversen\db;
use modules\image\module as imageModule;

class module extends imageModule {
    /**
     * method for inserting a file into the database
     * (access control is cheched in controller file)
     *
     * @return boolean true on success or false on failure
     */
    public function insertFile ($values = null) {
        $db = new db();

        $options['maxsize'] = $this->maxsize;
        $options['allow_mime'] = $this->allowMime;

        $tmp_file = sys_get_temp_dir() . "/" . uniqid();
        file_put_contents($tmp_file, $values['file_org']);
         
        $this->scaleImage(
                    $tmp_file, 
                    $tmp_file . "-med", 
                    conf::getModuleIni('image_scale_width'));    
        $values['file'] = file_get_contents($tmp_file . "-med");
        
        $this->scaleImage(
                    $tmp_file, 
                    $tmp_file . "-thumb", 
                    conf::getModuleIni('image_scale_width_thumb'));
        $values['file_thumb'] = file_get_contents($tmp_file . "-thumb");

        unlink($tmp_file); 
        unlink($tmp_file . "-med"); 
        unlink($tmp_file . "-thumb");
        
        $res = $db->insert($this->fileTable, $values);
        return $res;
    }  
}
