<?php


/**
 * class content files is used for keeping track of file changes
 * in db. Uses object fileUpload
 *
 * @package image
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
    public static $allowMime = 
        array ('image/gif', 'image/jpeg', 'image/pjpeg', 'image/png');

    /**
     *
     * constructor sets init vars
     */
    function __construct($options = null){
         self::$options = $options;
    }

    public static function init ($options = null){
        self::$options = $options;
        self::$scaleWidth = config::getModuleIni('image_scale_width');
        self::$path = '/image';
        self::$fileTable = 'image';
        self::$maxsize = config::getModuleIni('image_max_size');
  
    }

    public static function setFileId ($frag = 2){
        self::$fileId = uri::$fragments[$frag];
    }
    
    public static function getImgTag ($row, $size = "file_org", $options = array ()) {
        return $img_tag = html::createHrefImage(
                "/image/download/$row[id]/$row[title]?size=file_org", 
                "/image/download/$row[id]/$row[title]?size=$size", 
                $options);

    }

   /**
    * method for creating a form for insert, update and deleting entries
    * in module_system module
    *
    *
    * @param string    method (update, delete or insert)
    * @param int       id (if delete or update)
    */
    public static function viewFileForm($method, $id = null, $values = array(), $caption = null){
        
        html::formStartAry(array('id' => 'image_upload_form'));
        if ($method == 'delete' && isset($id)) {
            $legend = lang::translate('Delete image');
            html::legend($legend);
            html::submit('submit', lang::system('system_submit_delete'));
            echo html::getStr();
            return;
        }
        
        if ($method == 'delete_all' && isset($id)) {
            $legend = lang::translate('Delete all images');
            html::legend($legend);
            html::submit('submit', lang::system('system_submit_delete'));
            html::formEnd();
            echo html::getStr();
            return;
        }
        
        $legend = '';
        if (isset($id)) {
            $values = self::getSingleFileInfo($id);
            html::init($values, 'submit'); 
            $legend = lang::translate('Edit image');
            $submit = lang::system('system_submit_update');
        } else {
            html::init(html::specialEncode($_POST), 'submit'); 
            $legend = lang::translate('Add image');
            $submit = lang::system('system_submit_add');
        }
        
        html::legend($legend);
        html::label('abstract', lang::translate('Abstract'));
        html::textareaSmall('abstract');
        
        if (config::getModuleIni('image_user_set_scale')) {
            html::label('scale_size', lang::translate('Image width in pixels, e.g. 100'));
            html::text('scale_size');
        }
        
        $bytes = config::getModuleIni('image_max_size');
        html::fileWithLabel('file', $bytes);
            
        html::submit('submit', $submit);
        html::formEnd();
        echo html::getStr();
    }
    
    public static function rpcServer () {
        
        $reference = @$_GET['reference'];
        $parent_id = @$_GET['parent_id'];
        
        if (empty($reference) || empty($parent_id)) {
            return;
        }
        
        $rows = self::getAllFilesInfo(
                array(
                    'reference' => $reference, 
                    'parent_id' => $parent_id)
                );
        foreach ($rows as $key => $val) {
            $rows[$key]['url_m'] = "/image/download/$val[id]/" . strings::utf8SlugString($val['title']);
            $rows[$key]['url_s'] = "/image/download/$val[id]/$val[title]?size=file_thumb";
            
            //$str = html::specialEncode($val['abstract']);
            $str = strings::sanitizeUrlRigid(html::specialDecode($val['abstract']));
            $rows[$key]['title'] = $str; 
            
        }
        
        $photos = array ('images' => $rows);
        echo json_encode($photos);
    }
    
    public static function getFullWebPath ($row, $size = null) {
         $str = "/image/download/$row[id]/$row[title]";
        //$str = "/image/download/$row[id]/" . strings::utf8SlugString($row['title']);
        if ($size) {
            //$str.= "?size=$size";
        } else {
            //$str.= "?size=file_med";
        }
        //echo $str; die;
        return $str;
    }
    
    /**
     * methoding for setting med size if allowed
     */
    public static function getMedSize () {
        $med_size = 0;
        if (isset($_POST['scale_size']) && !empty($_POST['scale_size'])  && $_POST['scale_size'] > 0 ) {
            $med_size = (int)$_POST['scale_size']; 
            unset($_POST['scale_size']);
        }
        if (!$med_size) {
            $med_size = config::getModuleIni('image_scale_width');
        }
        return $med_size;
    }

    /**
     * method for inserting a module into the database
     * (access control is cheched in controller file)
     *
     * @return boolean true on success or false on failure
     */
    public static function insertFile () {
        $db = new db();

        $_POST = html::specialDecode($_POST);
        $options['filename'] = 'file';
        $options['maxsize'] = self::$maxsize;
        $options['allow_mime'] = self::$allowMime;
        
        $med_size = image::getMedSize();

        // get fp - will also check for error in upload
        $fp = upload_blob::getFP('file', $options);
        if (!$fp) {
            self::$errors = upload_blob::$errors;
            return false;
        } 
        
        $values['file_org'] = $fp;
        
        // we got a valid file pointer where we checked for errors
        // now we use the tmp name for the file when scaling. Only
        // scale if an scaleWidth has been set. 
        
        self::scaleImage(
                $_FILES['file']['tmp_name'], 
                $_FILES['file']['tmp_name'] . "-med", 
                $med_size);
        
        $fp_med = fopen($_FILES['file']['tmp_name'] . "-med", 'rb');
        $values['file'] = $fp_med;
        
        self::scaleImage(
                $_FILES['file']['tmp_name'], 
                $_FILES['file']['tmp_name'] . "-thumb", 
                config::getModuleIni('image_scale_width_thumb'));
        $fp_thumb = fopen($_FILES['file']['tmp_name'] . "-thumb", 'rb'); 
        $values['file_thumb'] = $fp_thumb;
        
        $values['title'] = $_FILES['file']['name'];
        $values['mimetype'] = $_FILES['file']['type'];
        $values['parent_id'] = self::$options['parent_id'];
        $values['reference'] = self::$options['reference'];
        $values['abstract'] = $_POST['abstract'];
        $values['user_id'] = session::getUserId();
        
        $bind = array(
            'file_org' => PDO::PARAM_LOB, 
            'file' => PDO::PARAM_LOB,
            'file_thumb' => PDO::PARAM_LOB,);
        $res = $db->insert(self::$fileTable, $values, $bind);
        return $res;
    }

    /**
     *
     * @param type $image the image file to scale from
     * @param type $thumb the image file to scale to
     * @param type $width the x factor or width of the image
     * @return type 
     */
    public static function scaleImage ($image, $thumb, $width){
        include_once "imagescale.php";
        $res = imagescale::byX($image, $thumb, $width);
        if (!empty(imagescale::$errors)) self::$errors = imagescale::$errors;
        return $res;
    }

    /**
     * validate before insert update. 
     * @param type $mode 
     */
    public static function validateInsert($mode = false){
        //$_POST = html::specialEncode($_POST);
        if ($mode != 'update') {
            if (empty($_FILES['file']['name'])){
                self::$errors[] = lang::translate('No file was specified');
            }
        }
    }

    /**
     * method for delting a file
     *
     * @param   int     id of file
     * @return  boolean true on success and false on failure
     *
     */
    public function deleteFile($id){
        $db = new db();
        $res = $db->delete(self::$fileTable, 'id', $id);
        return $res;
    }
   
    public static function subModulePreContent ($options){
        return '';
        $rows = self::getAllFilesInfo($options);
        if (session::isAdmin()){
            return self::displayFiles($rows, $options);
        }
    }
    
    /**
     * get admin when operating as a sub module
     * @param array $options
     * @return string  
     */
    public static function subModuleAdminOption ($options){
        $str = "";
        $url = moduleloader::buildReferenceURL('/image/add', $options);
        $add_str= lang::translate('Edit images');
        $str.= html::createLink($url, $add_str);
        return $str;
    }
    
    /**
     * get admin options as ary ('text', 'url', 'link') when operating as a sub module
     * @param array $options
     * @return array $ary  
     */
    public static function subModuleAdminOptionAry ($options){
        $ary = array ();
        $url = moduleloader::buildReferenceURL('/image/add', $options);
        $text = lang::translate('Edit images');
        $ary['link'] = html::createLink($url, $text);
        $ary['url'] = $url;
        $ary['text'] = $text;
        return $ary;
    }
    
    /**
     * deletes images from a reference and a parent_id
     * @param type $parent
     * @param type $reference
     * @return type
     */
    public static function deleteReferenceId($parent, $reference) {

        return db_q::setDelete('image')->filter('reference =', $reference)
                ->condition('AND')
                ->filter('parent_id =', $parent)
                ->exec();
               
    }

    /**
     * displays all files from db rows and options
     * @param array $rows
     * @param array $options
     * @return string $html
     */
    public static function displayFiles($rows, $options){
        
        $str = "";

        foreach ($rows as $val){
            $title = lang::translate('Download');
            $title.= MENU_SUB_SEPARATOR_SEC;
            $title.= htmlspecialchars($val['title']);
            
            $link_options = array ('title' => htmlspecialchars($val['abstract'])); 
            $str.= html::createLink(self::$path . "/download/$val[id]/$val[title]", $title, $link_options);

                $options['id'] = $val['id'];
                $url = moduleloader::buildReferenceURL('/image/edit', $options);     
                $str.= MENU_SUB_SEPARATOR_SEC;
                $str.= html::createLink($url, lang::system('system_submit_edit'));
                $url = moduleloader::buildReferenceURL('/image/delete', $options);
                $str.= MENU_SUB_SEPARATOR;
                $str.= html::createLink($url, lang::system('system_submit_delete'));
            
            $str.= "<br />\n";
        }
        
        $info = self::getAllFilesInfo($options);
        if (!empty($info)) { 
            $url = $url = "/image/delete_all/$options[parent_id]/0/$options[reference]";
            $title = lang::translate('Delete all images');
            $str.= html::createLink($url, $title);
        }
        return $str;
    }
    
    public static function subModuleInlineContent($options){
        //self::init($options);
        return '';
        if (config::getModuleIni('image_submodule_disable_insert')) {
            return '';
        }
        
        $str = '';
        $files = self::getAllFilesInfo($options);
        if (!empty($files)){
            foreach ($files as $val) {
                $file_url = self::$path . "/download/$val[id]/$val[title]";
                $str.="<div id=\"content_image\">\n";
                $options = array (
                    'width' => self::$scaleWidth,
                    'alt' => html::specialEncode($val['abstract']),
                    'title' => html::specialEncode($val['abstract'])
                    );
                $str.= html::createImage($file_url, $options);
                $str.="</div><br />";
            }
        }
        return $str;
    }

    public static function getAllFilesInfo($options){
        $db = new db();
        $search = array (
            'parent_id' => $options['parent_id'],
            'reference' => $options['reference']
        );

        $fields = array ('id', 'parent_id', 'title', 'abstract', 'published', 'created');
        $rows = $db->selectAll(self::$fileTable, $fields, $search, null, null, 'created', false);
        foreach ($rows as $key => $row) {
            $rows[$key]['image_url'] = self::getFullWebPath($row);
        } 
        
        return $rows;
    }

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

    // {{{ getFile()
    /**
     * method for fetching one file
     *
     * @return array assoc row with selected module
     */
    public static function getFile($size = null){
        $db = new db();
        
        if (!$size) $size = 'file';
        if ($size != 'file' || $size != 'file_thumb' || $size != 'file_org') {
            $size = 'file';
        }
        
        $db->selectOne(self::$fileTable, 'id', self::$fileId, array($size));
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

    public static function updateFile () {
        $med_size = self::getMedSize();
        $values = db::prepareToPost();
        
        if (!empty($_FILES['file']['name']) ){
            $options['filename'] = 'file';
            $options['maxsize'] = self::$maxsize;
            $options['allow_mime'] = self::$allowMime;

            // get fp - will also check for error in upload
            $fp = upload_blob::getFP('file', $options);
            if (!$fp) {
                self::$errors = upload_blob::$errors;
                return false;
            } 
            
            $values['file_org'] = $fp;
            
            if (empty($med_size)) {
                $med_size = config::getModuleIni('image_scale_width');
            }

            self::scaleImage(
                    $_FILES['file']['tmp_name'], 
                    $_FILES['file']['tmp_name'] . "-med", 
                    $med_size);
            $fp_med = fopen($_FILES['file']['tmp_name'] . "-med", 'rb');
            $values['file'] = $fp_med;
            //die;
            self::scaleImage(
                    $_FILES['file']['tmp_name'], 
                    $_FILES['file']['tmp_name'] . "-thumb", 
                    config::getModuleIni('image_scale_width_thumb'));
            $fp_thumb = fopen($_FILES['file']['tmp_name'] . "-thumb", 'rb'); 
        //}
            $values['file_thumb'] = $fp_thumb;
            //if (!$res) return false;

            $values['title'] = $_FILES['file']['name'];
            $values['mimetype'] = $_FILES['file']['type'];
            $values['parent_id'] = self::$options['parent_id'];
            $values['reference'] = self::$options['reference'];

            $bind = array(
            'file_org' => PDO::PARAM_LOB, 
            'file' => PDO::PARAM_LOB,
            'file_thumb' => PDO::PARAM_LOB,);
        }
        $db = new db();
        
        $res = $db->update(self::$fileTable, $values, self::$fileId, $bind);
        return $res;
    }
    
    public static function viewIframeFileFormInsert($options){
        //$options['redirect'] = 
        $redirect = moduleloader::buildReferenceURL('/image/add_ajax', self::$options);
        if (isset($_POST['submit'])){
            self::validateInsert();
            if (!isset(self::$errors)){
                $res = self::insertFile($options);
                if ($res){
                    session::setActionMessage(lang::translate('Image was added'));
 
                    http::locationHeader($redirect);
                } else {
                    html::errors(self::$errors);
                }
            } else {
                html::errors(self::$errors);
            }
        }
        self::viewFileForm('insert');
    }
    
    public static function viewFileFormInsertClean($options){
        // $redirect = moduleloader::buildReferenceURL('/image/add', self::$options);
        if (isset($_POST['submit'])){
            self::validateInsert();
            if (!isset(self::$errors)){
                $res = self::insertFile($options);
                if ($res){
                    session::setActionMessage(lang::translate('Image was added'));
                    http::locationHeader($redirect);
                } else {
                    html::errors(self::$errors);
                }
            } else {
                html::errors(self::$errors);
            }
        }
        self::viewFileForm('insert');
    }
    
    /**
     * view form for uploading a file.
     * @param type $options
     */
    public static function viewFileFormInsert($options){
        if (config::getModuleIni('image_redirect_parent')) {
            $redirect = moduleloader_reference::getParentEditUrlFromOptions(self::$options);
        } else {
            $redirect = moduleloader::buildReferenceURL('/image/add', self::$options);
        }

        if (isset($_POST['submit'])){
            self::validateInsert();
            if (!isset(self::$errors)){
                $res = self::insertFile($options);
                if ($res){
                    session::setActionMessage(lang::translate('Image was added'));
                    http::locationHeader($redirect);
                } else {
                    html::errors(self::$errors);
                }
            } else {
                html::errors(self::$errors);
            }
        }
        self::viewFileForm('insert');
    }

    public static function viewFileFormDelete(){
        $redirect = moduleloader::buildReferenceURL('/image/add', self::$options);
        if (isset($_POST['submit'])){
            if (!isset(self::$errors)){
                $res = self::deleteFile(self::$fileId);
                if ($res){
                    session::setActionMessage(lang::translate('Image was deleted'));
                    $header = "Location: " . $redirect;
                    header($header);
                    exit;
                }
            } else {
                html::errors(self::$errors);
            }
        }
        self::viewFileForm('delete', self::$fileId);
    }
    
    public function deleteAll($parent, $reference){
        $db = new db();
        $search = array ('parent_id' => $parent, 'reference' => $reference);
        $res = $db->delete(self::$fileTable, null, $search);
        return $res;
    }
    
    public static function viewFileFormDeleteAll(){
        $redirect = moduleloader::buildReferenceURL('/image/add', self::$options);
        if (isset($_POST['submit'])){
            if (!isset(self::$errors)){
                $res = self::deleteAll(self::$options['parent_id'], self::$options['reference']);
                if ($res){
                    session::setActionMessage(lang::translate('All images has been deleted'));
                    $header = "Location: " . $redirect;
                    header($header);
                    exit;
                }
            } else {
                html::errors(self::$errors);
            }
        }
        self::viewFileForm('delete_all', self::$fileId);
    }

    public function viewFileFormUpdate(){
        $redirect = moduleloader::buildReferenceURL('/image/add', self::$options);
        if (isset($_POST['submit'])){
            self::validateInsert('update');
            if (!isset(self::$errors)){
                $res = self::updateFile();
                if ($res){
                    session::setActionMessage(lang::translate('Image was updated'));
                    $header = "Location: " . $redirect;
                    header($header);
                    exit;
                } else {
                    html::errors(self::$errors);
                }
            } else {
                html::errors(self::$errors);
            }
        }
        self::viewFileForm('update', self::$fileId);
    }

    public static function getImageSize () {
        $size = null;
        if (!isset($_GET['size'])) {
            $size = 'file';
        } else {
            $size = $_GET['size'];
        }
        //if (!$size) $size = 'file';
        if ($size != 'file' && $size != 'file_thumb' && $size != 'file_org') {
            $size = 'file';
        }
        return $size;
    }

    public static function downloadController (){

        image::init();
        image::setFileId($frag = 2);
        
        $size = self::getImageSize(); 
        $file = image::getFile($size);
        if (empty($file)) {
            moduleloader::setStatus(404);
            return;
        }
        
        
        http::cacheHeaders();
        if (isset($file['mimetype']) && !empty($file['mimetype'])) {
            header("Content-type: $file[mimetype]");
        }
        echo $file[$size];
        die;
    }
}
