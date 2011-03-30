<?php

// we use the files module and the content/article module
include_module ('content/article');
include_module ('files');

class image extends files {
    //public static $fileTable = 'content_image';
    //public static $basePath = '/content/image';

    function __construct($options){
        parent::__construct($options);
        $this->fileTable = 'content_image';
        $this->basePath = '/content/image';
        //self::$basePath = '/content/image';
    }

    public function insertFile () {
        $values = $this->prepareToPost();
        $values['parent_id'] = $this->id;

        $options['filename'] = 'file';
        $options['maxsize'] = $this->maxsize;

        $scale_image_width = get_module_ini('scale_image_width');
        $this->scaleImage($_FILES['file']['tmp_name'], $_FILES['file']['tmp_name'], $scale_image_width);

        $values['file'] = uploadBlob::getFP($options);
        $values['title'] = $_FILES['file']['name'];
        $values['mimetype'] = $_FILES['file']['type'];

        $bind = array('file' => PDO::PARAM_LOB);
        $res = $this->insert($this->fileTable, $values, $bind);
        return $res;
    }

    private function scaleImage ($image, $thumb, $length){
        require_once 'Image/Transform.php';

        //create transform driver object
        $it = Image_Transform::factory('GD');
        if (PEAR::isError($it)) {
            die($it->getMessage());
        }

        //load the original file
        $ret = $it->load($image);
        if (PEAR::isError($ret)) {
            die($ret->getMessage());
        }

        //scale 
        $ret = $it->scaleByLength($length);
        if (PEAR::isError($ret)) {
            die($ret->getMessage());
        }

        //save it into a different file
        $ret = $it->save($thumb);
        if (PEAR::isError($ret)) {
            die($ret->getMessage());
        }
    }
    // }}}

    public function displayImgTags(){

        $str = "";
        $files = $this->getAllFiles();
        if (!empty($files)){
            foreach ($files as $key => $val) {
                $file_url = $this->basePath . "/download/$val[parent_id]/$val[id]/$val[title]";
                $str.="<div id=\"content_image\"><img width=\"" . get_module_ini('scale_image_width') . "px\" src=\"$file_url\" alt=\"$val[abstract]\" /></div><br />";
            }
        }
        return $str;
    }

    public function displayFiles($id, $rows){
        $str = "";
        //$download_str = lang::translate('Download File');

        if (!empty($rows) && session::isAdmin()){
            $str.="<p><hr />" . lang::translate('Images') . "</p>\n";
        }
        foreach ($rows as $key => $val){
            if (session::isAdmin()){
                $str.= "<p>";
                $str.= "<a href=\"" . $this->basePath . "/download/$id/$val[id]/$val[title]\">$val[title]</a>";
                $str.=" | ";
                $str.="<a href=\"" . $this->basePath . "/edit/$id/$val[id]\">";
                $str.=lang::translate('Edit') . "</a>\n";
                $str.=" | ";
                $str.="<a href=\"" . $this->basePath . "/delete/$id/$val[id]\">";
                $str.=lang::translate('Delete');
                $str.="</a>\n";
            }
        }
        if (!empty($rows) && session::isAdmin()){
            $str.="</p>\n";
            $str.="<p>$val[abstract]</p>\n";
        }
        print $str;
    }
    // }}}
}