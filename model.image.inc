<?php

/**
 * model file for doing file uploads
 *
 * @package     content
 */

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
    public static $fileTable = 'image';

    // {{{ function __construct()
    /**
     *
     * constructor sets init vars
     */
    function __construct($options = null){
        $uri = URI::getInstance();
        self::$options = $options;
    }

    public static function setFileId (){
        self::$fileId = uri::$fragments[2];
    }
    // }}}
    // used when invoking module as submodule.
    public static function subModuleAdminOptionLink ($options){
        $get = http_build_query($options);
        $url = '/image/add?' . $get;
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
        $fields_captions =
            array(
                'abstract' => lang::translate('image_form_abstract'),
                'file' => lang::translate('image_form_image'),

        );
        $fields = array('id', 'abstract', 'file');
        $tf = new mySqlForm(self::$fileTable, $fields, $values);
        $tf->setLabels($fields_captions);

        if (isset($id)){
            if ($method == 'delete'){
                if (empty($caption)) $caption = lang::translate('image_delete_label');
            } else {
                //$fields = array('id', 'abstract', 'file');
                $tf = new mySqlForm(self::$fileTable, $fields, $values);
                $tf->setLabels($fields_captions);
                if (empty($caption)) $caption = lang::translate('image_edit_label');
            }
            $tf->setMethod($method, $id);

        } else {
            $tf->setMethod($method);
            if (empty($caption)) $caption = lang::translate('image_add_label');
        }
        $tf->createForm('', 'post',  'content_file', '', 'multipart/form-data',  $caption);
    }

    // {{{ insertModuleRelease()
    /**
     * method for inserting a module into the database
     * (access control is cheched in controller file)
     *
     * @return boolean true on success or false on failure
     */
    public function insertFile () {
        $values = $this->prepareToPost();
        $values['parent_id'] = self::$options['parent_id'];
        $values['reference'] = self::$options['reference'];

        $options['filename'] = 'file';
        $options['maxsize'] = self::$maxsize;
        //$options['allow_mime'] = array("application/x-gzip");
        $scale_image_width = get_module_ini('image_scale_width');
        self::scaleImage($_FILES['file']['tmp_name'], $_FILES['file']['tmp_name'], $scale_image_width);

        $values['file'] = uploadBlob::getFP($options);
        $values['title'] = $_FILES['file']['name'];
        $values['mimetype'] = $_FILES['file']['type'];

        $bind = array('file' => PDO::PARAM_LOB);
        $res = $this->insert(self::$fileTable, $values, $bind);
        return $res;



    }
    // }}}

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
        return self::displayFiles($rows, $options);

    }
    // {{{ displayUserModuleReleases($id, $rows)
    public static function displayFiles($rows, $options){
        $str = "";
        if (!empty($rows)){
            $str.= lang::translate('image_attached') . "<br />";
        }

        $get = http_build_query($options);
        foreach ($rows as $key => $val){
            $title = lang::translate('image_download');
            $title.= MENU_SUB_SEPARATOR_SEC;
            $title.= $val['title'];
            
            $options = array ('title' => $val['abstract']); 
            $str.= html::createLink("/image/download/$val[id]/$val[title]", $title, $options);


            if (session::isAdmin()){
                $str.= MENU_SUB_SEPARATOR_SEC;
                $str.= html::createLink("/image/edit/$val[id]?$get", lang::translate('files_edit'));
                $str.= MENU_SUB_SEPARATOR;
                $str.= html::createLink("/image/delete/$val[id]?$get", lang::translate('files_delete'));
            }
            $str.= "<br />\n";
        }

        if (session::isAdmin()){
            $add_str= lang::translate('image_add');
            $str.= html::createLink("/image/add?$get", $add_str);
        }

        return $str;
    }
    // }}}
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
            //echo "not upload"; die;
        //} else {
            // get a file pointer
            $options['filename'] = 'file';
            $options['maxsize'] = self::$maxsize;

            $scale_image_width = get_module_ini('image_scale_width');
            self::scaleImage($_FILES['file']['tmp_name'], $_FILES['file']['tmp_name'], $scale_image_width);

            $values['file'] = uploadBlob::getFP($options);
            $values['title'] = $_FILES['file']['name'];
            $values['mimetype'] = $_FILES['file']['type'];

            $bind = array('file' => PDO::PARAM_LOB);
        }
        $res = $this->update(self::$fileTable, $values, self::$fileId, $bind);
        return $res;
    }
    // }}}
    public function viewFileFormInsert(){
        if (isset($_POST['submit'])){
            $this->validateInsert();
            if (!isset(self::$errors)){
                $res = $this->insertFile();
                if ($res){
                    session::setActionMessage(lang::translate('image_file_added'));
                    $redirect = "Location: " . self::$options['redirect'];
                    header($redirect);
                }
            } else {
                view_form_errors(self::$errors);
            }
        }
        $this->viewFileForm('insert');
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
}
