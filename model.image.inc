<?php


/**
 * @ignore
 */
include_once "upload.php";
include_once "mySqlForm.php";

/**
 * class content files is used for keeping track of file changes
 * in db. Uses object fileUpload
 *
 * @package content
 */
class image extends db {


    public static $errors = null;
    public static $status = null;
    public static $parent_id;
    public static $fileId;
    public static $maxsize = 2000000; // 2 mb max size
    public static $options = array();
    public static $path = '/image';
    public static $fileTable = 'image';
    public static $scaleWidth;

    // {{{ function __construct()
    /**
     *
     * constructor sets init vars
     */
    function __construct($options = null){
        $uri = URI::getInstance();
        self::$options = $options;
        self::$scaleWidth = get_module_ini('image_scale_width');
    }

    public static function init (){
        self::$path = '/image';
        self::$fileTable = 'image';
        self::$scaleWidth = get_module_ini('image_scale_width');
        //parent::__construct($options);
    
    }

    public static function setFileId (){
        self::$fileId = uri::$fragments[2];
    }
    // }}}
    // used when invoking module as submodule.
    public static function subModuleAdminOptionLink ($options){
        $get = http_build_query($options);
        $url = self::$path . '/add?' . $get;
        $title = lang::translate('image_add_image');
        return html::createLink($url, $title);
    }


    // {{{ viewFilesForm($method, $id = null, $values = array(), $caption = null)
   /**
    * method for creating a form for insert, update and deleting entries
    * in module_system module
    *
    *
    * @param string    method (update, delete or insert)
    * @param int       id (if delete or update)
    */
    public function viewFileForm($method, $id = null, $values = array(), $caption = null){
        
        html::formStart('image_upload_form');
        if ($method == 'delete' && isset($id)) {
            $legend = lang::translate('image_delete_label');
            html::legend($legend);
            html::submit('submit', lang::system('system_submit_delete'));
            echo html::getStr();
            return;
        }
        
        $legend = '';
        if (isset($id)) {
            $values = self::getSingleFileInfo($id);
            html::init($values, 'submit'); 
            $legend = lang::translate('image_edit_label');
            $submit = lang::system('system_submit_update');
        } else {
            $legend = lang::translate('image_add_label');
            $submit = lang::system('system_submit_add');
        }
        
        html::legend($legend);
        html::label('abstract', lang::system('image_abstract_label'));
        html::textareaSmall('abstract');
        html::label('file', lang::system('image_file_label'));
        html::file('file');
        html::submit('submit', $submit);
        echo html::getStr();
    }
    

    /**
     * method for inserting a module into the database
     * (access control is cheched in controller file)
     *
     * @return boolean true on success or false on failure
     */
    public static function insertFile ($options) {
        $db = new db();

        $values = db::prepareToPost();
        $values['parent_id'] = $options['parent_id'];
        $values['reference'] = $options['reference'];

        $options['filename'] = 'file';
        $options['maxsize'] = self::$maxsize;
        //$options['allow_mime'] = array("application/x-gzip");
        echo self::$scaleWidth; 
        self::scaleImage($_FILES['file']['tmp_name'], $_FILES['file']['tmp_name'], self::$scaleWidth);

        $values['file'] = uploadBlob::getFP($options);
        $values['title'] = $_FILES['file']['name'];
        $values['mimetype'] = $_FILES['file']['type'];

        $bind = array('file' => PDO::PARAM_LOB);
        $res = $db->insert(self::$fileTable, $values, $bind);
        return $res;
    }

    public static function scaleImage ($image, $thumb, $length){
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


    // {{{ validateInsert()
    /**
     * method for validating a post before insert
     */
    public function validateInsert($mode = false){
        if (empty($_FILES['file']['name'])){
            self::$errors[] = lang::translate('image_no_file_specified');
        } else {
            if ($_FILES['file']['error'] != 0) {
                $error_message = file_upload_error_message($_FILES['file']['error']);
                self::$errors[] = $error_message;
            }
            if ($_FILES['file']['size'] > return_bytes(ini_get('post_max_size'))){
                self::$errors[] = lang::translate('image_file_too_big') . ' ' .
                              lang::transate('image_maximum_size_is') . " " . ini_get('post_max_size');
            }
        }
    }
    // }}}
    // {{{ validateInsert()
    /**
     * method for validating a post before insert
     */
    public function validateUpdate($mode = false){
        if (!empty($_FILES['file']['name'])){
            if ($_FILES['file']['error'] != 0) {
                $error_message = file_upload_error_message($_FILES['file']['error']);
                self::$errors[] = $error_message;
            }
            if ($_FILES['file']['size'] > return_bytes(ini_get('post_max_size'))){
                self::$errors[] = lang::translate('image_file_too_big')  . ' ' .
                              lang::transate('image_maximum_size_is') .  " " . ini_get('post_max_size');
            }
        }
    }
    // }}}
    // {{{ function deleteFile($id) $id = fileId
    /**
     * method for delting a file
     *
     * @param   int     id of file
     * @return  boolean true on success and false on failure
     *
     */
    public function deleteFile($id){
        $res = $this->delete(self::$fileTable, 'id', $id);
        return $res;
    }
    // }}}
    public static function subModulePreContent ($options){
        $rows = self::getAllFilesInfo($options);
        if (session::isAdmin()){
            return self::displayFiles($rows, $options);
        }
        return '';

    }
    // {{{ displayUserModuleReleases($id, $rows)
    public static function displayFiles($rows, $options){
        $str = "";

        $get = http_build_query($options);
        if (session::isAdmin()){
            $str.= lang::translate('image_attached') . MENU_SUB_SEPARATOR_SEC;
            $add_str= lang::translate('image_add');
            $str.= html::createLink(self::$path . "/add?$get", $add_str);
            $str.= "<br />";
        }
        foreach ($rows as $key => $val){
            $title = lang::translate('image_download');
            $title.= MENU_SUB_SEPARATOR_SEC;
            $title.= $val['title'];
            
            $options = array ('title' => $val['abstract']); 
            $str.= html::createLink(self::$path . "/download/$val[id]/$val[title]", $title, $options);

            if (session::isAdmin()){
                $str.= MENU_SUB_SEPARATOR_SEC;
                $str.= html::createLink(self::$path . "/edit/$val[id]?$get", lang::translate('image_edit_image'));
                $str.= MENU_SUB_SEPARATOR;
                $str.= html::createLink(self::$path . "/delete/$val[id]?$get", lang::translate('image_delete_image'));
            }
            $str.= "<br />\n";
        }

        return $str;
    }
    // }}}
    static function subModuleInlineContent($options){
        self::init();
        $str = '';
        $files = self::getAllFilesInfo($options);
        if (!empty($files)){
            foreach ($files as $key => $val) {
                $file_url = self::$path . "/download/$val[id]/$val[title]";
                $str.="<div id=\"content_image\">\n";
                $options = array (
                    'width' => self::$scaleWidth . "px",
                    'alt' => $val['abstract'],
                    'title' => $val['abstract']
                    );
                $str.= html::createImage($file_url, $options);
                $str.="</div><br />";
            }
        }
        return $str;
    }

    

    // {{{ getModuleReleases($moduleId = null)
    /**
     * method for fetching modules belonging to a user
     *
     * @return array assoc rows of modules belonging to user
     */
    public static function getAllFilesInfo($options){
        $db = new db();
        $search = array (
            'parent_id' => $options['parent_id'],
            'reference' => $options['reference']
        );

        $fields = array ('id', 'parent_id', 'title', 'abstract', 'published', 'created');
        $rows = $db->selectAll(self::$fileTable, $fields, $search, null, null, 'created', false);
        return $rows;
    }
    // }}}
    public static function getSingleFileInfo($id = null){
        if (!$id) $id = self::$fileId;
        $db = new db();
        $search = array (
            'id' => $id
        );

        $fields = array ('id', 'parent_id', 'title', 'abstract', 'published', 'created', 'reference');
        $row = $db->selectOne(self::$fileTable, null, $search, $fields, null, 'created', false);
        return $row;
    }
    // }}}

    // {{{ displayUserModules
    /**
     * method for displaying all modules belonging to a user
     *
     */

    // {{{ getFile()
    /**
     * method for fetching one file
     *
     * @return array assoc row with selected module
     */
    public static function getFile(){
        $db = new db();
        $row = $db->selectOne(self::$fileTable, 'id', self::$fileId);
        return $row;
    }
    // }}}
    // {{{ updateModuleRelease()
    /**
     * method for updating a module in database
     * (access control is cheched in controller file)
     *
     * @return boolean true on success or false on failure
     */
    public function updateFile () {
        $values = $this->prepareToPost();
        if (is_uploaded_file($_FILES['file']['tmp_name']) ){
            $options['filename'] = 'file';
            $options['maxsize'] = self::$maxsize;

            self::scaleImage($_FILES['file']['tmp_name'], $_FILES['file']['tmp_name'], self::$scaleWidth);

            $values['file'] = uploadBlob::getFP($options);
            $values['title'] = $_FILES['file']['name'];
            $values['mimetype'] = $_FILES['file']['type'];

            $bind = array('file' => PDO::PARAM_LOB);
        }
        $res = $this->update(self::$fileTable, $values, self::$fileId, $bind);
        return $res;
    }
    // }}}
    public function viewFileFormInsert($options){
        if (isset($_POST['submit'])){
            self::validateInsert();
            if (!isset(self::$errors)){
                $res = self::insertFile($options);
                if ($res){
                    session::setActionMessage(lang::translate('image_file_added'));
                    $redirect = "Location: " . $options['redirect'];
                    header($redirect);
                }
            } else {
                view_form_errors(self::$errors);
            }
        }
        self::viewFileForm('insert');
    }

    public function viewFileFormDelete(){
        if (isset($_POST['submit'])){
            if (!isset(self::$errors)){
                $res = $this->deleteFile(self::$fileId);
                if ($res){
                    session::setActionMessage(lang::translate('image_file_deleted'));
                    $header = "Location: " . self::$options['redirect'];
                    header($header);
                }
            } else {
                view_form_errors(self::$errors);
            }
        }
        $this->viewFileForm('delete', self::$fileId);
    }

    public function viewFileFormUpdate(){
        if (isset($_POST['submit'])){
            $this->validateUpdate();
            if (!isset(self::$errors)){
                $res = $this->updateFile();
                if ($res){
                    session::setActionMessage(lang::translate('image_file_edited'));
                    $header = "Location: " . self::$options['redirect'];
                    header($header);
                }
            } else {
                view_form_errors(self::$errors);
            }
        }
        $this->viewFileForm('update', self::$fileId);
    }

    public static function addController (){
        if (!include_module ($_GET['reference'])){
            moduleLoader::$status['404'] = true;
            return;
        }

        $class = moduleLoader::modulePathToClassName($_GET['reference']);
        $link = $class::getLinkFromId($_GET['parent_id']);

        $headline = lang::translate('image_add_image') . MENU_SUB_SEPARATOR_SEC . $link;
        headline_message($headline);

        template::setTitle(lang::translate('image_add_image'));

        $options = array (
            'parent_id' => $_GET['parent_id'],
            'reference' => $_GET['reference'],
            'redirect' => $_GET['return_url']);

        //$image = new image($options);
        self::viewFileFormInsert($options);
    }

    public static function deleteController () {
        if (!include_module ($_GET['reference'])){
            moduleLoader::$status['404'] = true;
            return;
        }

        $class = moduleLoader::modulePathToClassName($_GET['reference']);
        $link = $class::getLinkFromId($_GET['parent_id']);

        $headline = lang::translate('image_delete_image') . MENU_SUB_SEPARATOR_SEC . $link;
        headline_message($headline);

        $options = array (
            'redirect' => $_GET['return_url']);
        $image = new image($options);

        image::setFileId();
        $image->viewFileFormDelete();
    }

    public static function downloadController (){
        $image = new image();
        image::setFileId();
        $file = $image->getFile();

        header("Content-type: $file[mimetype]");
        echo $file['file'];
        die;
    }

    public static function editController (){
        if (!include_module ($_GET['reference'])){
            moduleLoader::$status['404'] = true;
            return;
        }

        $class = moduleLoader::modulePathToClassName($_GET['reference']);
        $link = $class::getLinkFromId($_GET['parent_id']);

        $headline = lang::translate('image_edit_image') . MENU_SUB_SEPARATOR_SEC . $link;
        headline_message($headline);

        $options = array (
            'redirect' => $_GET['return_url']);

        image::setFileId();
        $image = new image($options);
        $image->viewFileFormUpdate();
    }
}