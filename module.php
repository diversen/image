<?php

use diversen\conf;
use diversen\db;
use diversen\db\q;
use diversen\html;
use diversen\http;
use diversen\imagescale;
use diversen\lang;
use diversen\layout;
use diversen\moduleloader;
use diversen\pagination;
use diversen\session;
use diversen\strings;
use diversen\template;
use diversen\upload\blob;
use diversen\uri;
use diversen\user;

/**
 * class content files is used for keeping track of file changes
 * in db. Uses object fileUpload
 *
 * @package image
 */
class image {


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
    
    public function rpcAction () {
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
            $rows[$key]['url_s'] = "/image/download/$val[id]/" . strings::utf8SlugString($val['title']) . "?size=file_thumb";
            $str = strings::sanitizeUrlRigid(html::specialDecode($val['abstract']));
            $rows[$key]['title'] = $str; 
        }
        
        $photos = array ('images' => $rows);
        echo json_encode($photos);
        die;
    }
    
    /**
     * add action
     * @return mixed
     */
    public function addAction() {
        if (!session::checkAccessFromModuleIni('image_allow_edit')) {
            return;
        }

        moduleloader::$referenceOptions = array('edit_link' => 'true');
        if (!moduleloader::includeRefrenceModule()) {
            moduleloader::setStatus(404);
            return;
        }

        $options = moduleloader::getReferenceInfo();
        $allow = conf::getModuleIni('image_allow_edit');
        
        // if allow is set to user - this module only allow user to edit his own images
        if ($allow == 'user') {
            $table = moduleloader::moduleReferenceToTable($options['reference']);
            
            // check if reference module is allowed to access image module
            if (!$this->checkReferenceTable($table)) {
                moduleloader::setStatus(403);
                return;
            }
            
            
            if (!user::ownID($table, $options['parent_id'], session::getUserId())) {
                moduleloader::setStatus(403);
                return;
            }
        }

        // set headline and title
        $headline = lang::translate('Add image') . MENU_SUB_SEPARATOR_SEC . moduleloader::$referenceLink;
        html::headline($headline);
        template::setTitle(lang::translate('Add image'));

        // set parent modules menu
        layout::setMenuFromClassPath($options['reference']);

        // display image module content
        self::init($options);
        self::viewFileFormInsert($options);
        $rows = self::getAllFilesInfo($options);
        echo self::displayFiles($rows, $options);
    }
    
    /**
     * check which module references are allowed to access image module
     * notice: if this ini setting is not set any module will be able to reference
     * the image module. This can lead to errors. So it should be set. 
     * @param string $table the db table which will make references to image, e.g. blog 
     * @return boolean $res
     */
    public function checkReferenceTable ($table) {
        
        $allow = conf::getModuleIni('image_allow_reference');
        if (!$allow) {
            return true;
        }   
        $allow = explode(',', $allow);
        if (in_array($table, $allow)) {
            return true;
        }
        return false;
    }

    /**
     * delete action
     * @return type
     */
    public function deleteAction() {
        if (!session::checkAccessFromModuleIni('image_allow_edit')) {
            return;
        }

        moduleloader::$referenceOptions = array('edit_link' => 'true');
        if (!moduleloader::includeRefrenceModule()) {
            moduleloader::$status['404'] = true;
            return;
        }

        $options = moduleloader::getReferenceInfo();
        $allow = conf::getModuleIni('image_allow_edit');

        // if allow is set to user - this module only allow user to edit his own images
        if ($allow == 'user') {
            //$table = moduleloader::moduleReferenceToTable($options['reference']);
            if (!user::ownID('image', $options['inline_parent_id'], session::getUserId())) {
                moduleloader::setStatus(403);
                return;
            }
        }

        // we now have a refrence module and a parent id wo work from.
        $link = moduleloader::$referenceLink;
        $headline = lang::translate('Delete image') . MENU_SUB_SEPARATOR_SEC . $link;
        html::headline($headline);

        template::setTitle(lang::translate('Delete image'));

        self::setFileId($frag = 3);
        self::init($options);
        self::viewFileFormDelete();
    }

    /**
     * delete_all action
     * @return type
     */
    public function delete_allAction() {
        if (!session::checkAccessFromModuleIni('image_allow_edit')) {
            return;
        }

        moduleloader::$referenceOptions = array('type' => 'edit');
        if (!moduleloader::includeRefrenceModule()) {
            moduleloader::$status['404'] = true;
            return;
        }

        // we now have a refrence module and a parent id wo work from.
        $link = moduleloader::$referenceLink;
        
        $options = moduleloader::getReferenceInfo();
        $allow = conf::getModuleIni('image_allow_edit');
        
                // if allow is set to user - this module only allow user to edit his own images
        if ($allow == 'user') {
            $table = moduleloader::moduleReferenceToTable($options['reference']);
            if (!user::ownID($table, $options['parent_id'], session::getUserId())) {
                moduleloader::setStatus(403);
                return;
            }
        }

        $headline = lang::translate('Delete all images') . MENU_SUB_SEPARATOR_SEC . $link;
        html::headline($headline);
        
        template::setTitle(lang::translate('Delete all images'));

        self::setFileId($frag = 3);
        self::init($options);
        self::viewFileFormDeleteAll();
    }

    /**
     * edit action
     * @return void
     */
    public function editAction() {
        
        if (!session::checkAccessFromModuleIni('image_allow_edit')) {
            return;
        }

        moduleloader::$referenceOptions = array('edit_link' => 'true');
        if (!moduleloader::includeRefrenceModule()) {
            moduleloader::$status['404'] = true;
            return;
        }

        $options = moduleloader::getReferenceInfo();
        $allow = conf::getModuleIni('image_allow_edit');

        // if allow is set to user - this module only allow user to edit his own images
        if ($allow == 'user') {
            if (!user::ownID('image', $options['inline_parent_id'], session::getUserId())) {
                moduleloader::setStatus(403);
                return;
            }
        }

        $link = moduleloader::$referenceLink;
        $headline = lang::translate('Edit image') . MENU_SUB_SEPARATOR_SEC . $link;
        html::headline($headline);
        template::setTitle(lang::translate('Edit image'));

        self::setFileId($frag = 3);

        // set parent modules menu
        layout::setMenuFromClassPath($options['reference']);
        self::init($options);
        self::viewFileFormUpdate();
    }

    /**
     * download controller
     */
    public function downloadAction() {
        self::downloadController();
    }

    /**
     * ajaxhtml action (test)
     * @param type $url
     */
    public function ajaxhtmlAction($url) {
        $h = new html();
        echo $h->fileHtml5($url);
    }

    /**
     * ajax action 
     * uploads from an ajax request. 
     */
    public function ajaxAction() {
        
        // check basic access
        if (!session::checkAccessFromModuleIni('image_allow_edit')) {
            moduleloader::setStatus(403);
            echo lang::translate("Access denied");
            die();
        }

        // if user - check if user owns parent reference
        $allow = conf::getModuleIni('image_allow_edit');
        if ($allow == 'user') {

            //$table = moduleloader::moduleReferenceToTable($_GET['reference']);
            if (!user::ownID('image', $_GET['parent_id'], session::getUserId())) {
                moduleloader::setStatus(403);
                echo lang::translate("Access denied");
                die();
            }
        }

        $options = array();
        $options['parent_id'] = $_GET['parent_id'];
        $options['reference'] = $_GET['reference'];

        // insert image
        self::init($options);
        self::validateInsert();
        if (!isset(self::$errors)) {
            $res = @self::insertFile();
            if ($res) {
                echo lang::translate('Image was added');
            } else {
                echo reset(self::$errors);
            }
        } else {
            echo reset(self::$errors);
        }
        die();
    }
    
    /**
     * admin action for checking user image uploads
     * @return boolean
     */
    public function adminAction () {
        if (!session::checkAccess('admin')) {
            return false;
        }
        
        layout::attachMenuItem('module', 
                array(
                    'title' => lang::translate('Images'), 
                    'url' => '/image/admin'));
        
        $per_page = 10;
        $total = q::numRows('image')->fetch();
        $p = new pagination($total);

        $from = @$_GET['from'];
        if (isset($_GET['delete'])) {
            $this->deleteFile($_GET['delete']);    
            http::locationHeader("/image/admin?from=$from", 
                    lang::translate('Image deleted'));
        }
        
        
        
        $rows = q::select('image', 'id, title, user_id')->
                order('created', 'DESC')->
                limit($p->from, $per_page)->
                fetch();
        
        echo "<table>";
        foreach ($rows as $row) {
            echo "<tr>";
            echo "<td>" . self::getImgTag($row, 'file_thumb') . "</td>";
            echo "<td>"; 
            echo user::getAdminLink($row['user_id']);
            echo "<br />";
            echo user::getProfileLink($row['user_id']);
            echo "<br />";
            echo html::createLink("/image/admin?delete=$row[id]&from=$from", lang::translate('Delete image'));
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo $p->getPagerHTML();
        
        
    }
    

    /**
     * init
     * @param type $options
     */
    public static function init ($options = null){
        self::$options = $options;
        self::$scaleWidth = conf::getModuleIni('image_scale_width');
        self::$path = '/image';
        self::$fileTable = 'image';
        self::$maxsize = conf::getModuleIni('image_max_size');
  
    }
    
    /**
     * check if image exists
     * @param int $id
     * @param string $reference
     * @return boolean
     */
    public static function imageExists ($id, $reference) {
        return q::select('image', 'id')->
                filter('parent_id =', $id)->condition('AND')->
                filter('reference =', $reference)->
                fetchSingle();
    }

    /**
     * set a files id
     * @param string $frag
     */
    public static function setFileId ($frag = 2){
        self::$fileId = uri::$fragments[$frag];
    }
    
    /**
     * get a image html tag
     * @param array $row
     * @param string $size
     * @param array $options
     * @return string $html image tag
     */
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
        
        html::$doUpload = true;
        $h = new html();
        
        $h->formStartAry(array('id' => 'image_upload_form'));
        if ($method == 'delete' && isset($id)) {
            $legend = lang::translate('Delete image');
            $h->legend($legend);
            $h->submit('submit', lang::translate('Delete'));
            echo $h->getStr();
            return;
        }
        
        if ($method == 'delete_all' && isset($id)) {
            $legend = lang::translate('Delete all images');
            $h->legend($legend);
            $h->submit('submit', lang::translate('Delete'));
            $h->formEnd();
            echo $h->getStr();
            return;
        }
        
        $legend = '';
        if (isset($id)) {
            $values = self::getSingleFileInfo($id);
            $h->init($values, 'submit'); 
            $h->legend($legend);
            $h->label('abstract', lang::translate('Abstract'));
            $h->textareaSmall('abstract');
            $legend = lang::translate('Edit image');
            $submit = lang::translate('Update');
        } else {
            $h->init(html::specialEncode($_POST), 'submit'); 
            $legend = lang::translate('Add image');
            $submit = lang::translate('Add');
            
            if (conf::getModuleIni('image_user_set_scale')) {
                $h->label('scale_size', lang::translate('Image width in pixels, e.g. 100'));
                $h->text('scale_size');
            }
            
            $bytes = conf::getModuleIni('image_max_size');
            $h->fileWithLabel('file', $bytes);
            
            $h->label('abstract', lang::translate('Abstract'));
            $h->textareaSmall('abstract');
            
        }
 
        $h->submit('submit', $submit);
        $h->formEnd();
        echo $h->getStr();
    }
    
    /**
     * return json encoded image rows from reference and parent_id
     * @return string $json
     */
    public static function rpcServer () {
        

    }
    
    /**
     * get full web path to a image.
     * @param type $row
     * @param type $size
     * @return string
     */
    public static function getFullWebPath ($row, $size = null) {
        $str = "/image/download/$row[id]/" . strings::utf8SlugString($row['title']);
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
            $med_size = conf::getModuleIni('image_scale_width');
        }
        return $med_size;
    }

    /**
     * method for inserting a module into the database
     * (access control is cheched in controller file)
     *
     * @return boolean true on success or false on failure
     */
    public static function insertFile ($input = 'file') {
        $db = new db();

        $_POST = html::specialDecode($_POST);
        $options['filename'] = $input;
        $options['maxsize'] = self::$maxsize;
        $options['allow_mime'] = self::$allowMime;
        
        $med_size = self::getMedSize();

        // get fp - will also check for error in upload
        $fp = blob::getFP('file', $options);
        if (!$fp) {
            self::$errors = blob::$errors;
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
                conf::getModuleIni('image_scale_width_thumb'));
        $fp_thumb = fopen($_FILES['file']['tmp_name'] . "-thumb", 'rb'); 
        $values['file_thumb'] = $fp_thumb;
        
        $values['title'] = $_FILES['file']['name'];
        $values['mimetype'] = $_FILES['file']['type'];
        $values['parent_id'] = self::$options['parent_id'];
        $values['reference'] = self::$options['reference'];
        $values['abstract'] = html::specialDecode($_POST['abstract']);
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
        $res = imagescale::byX($image, $thumb, $width);
        if (!empty(imagescale::$errors)) { 
            self::$errors = imagescale::$errors;
        }
        return $res;
    }

    /**
     * validate before insert update. 
     * @param type $mode 
     */
    public static function validateInsert($mode = false){

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
        $res = q::delete(self::$fileTable)->filter( 'id =', $id)->exec();
        return $res;
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
            
            $link_options = array('title' => htmlspecialchars($val['abstract']));
            $str.= html::createLink($val['image_url'], $title, $link_options);

            $options['id'] = $val['id'];
            $url = moduleloader::buildReferenceURL('/image/edit', $options);
            $str.= MENU_SUB_SEPARATOR_SEC;
            $str.= html::createLink($url, lang::translate('Edit'));
            $url = moduleloader::buildReferenceURL('/image/delete', $options);
            $str.= MENU_SUB_SEPARATOR;
            $str.= html::createLink($url, lang::translate('Delete'));

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
    
    /**
     * get info about all files from array with parent_id and reference
     * @param array $options
     * @return array $rows array of rows
     */
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

    /**
     * get info about a single image
     * @param int $id
     * @return array $row
     */
    public static function getSingleFileInfo($id = null){
        if (!$id) { 
            $id = self::$fileId;
        }
        $db = new db();
        $search = array (
            'id' => $id
        );

        $fields = array ('id', 'parent_id', 'title', 'abstract', 'published', 'created', 'reference');
        $row = $db->selectOne(self::$fileTable, null, $search, $fields, null, 'created', false);
        return $row;
    }

    /**
     * method for fetching one full file row
     * @return array $row
     */
    public static function getFile($size = null){
        $db = new db();
        
        if (!$size) { 
            $size = 'file';
        }
        if ($size != 'file' || $size != 'file_thumb' || $size != 'file_org') {
            $size = 'file';
        }
        
        $db->selectOne(self::$fileTable, 'id', self::$fileId, array($size));
        $row = $db->selectOne(self::$fileTable, 'id', self::$fileId);
        return $row;
    }

    /**
     * method for updating a module in database
     * @return boolean $res true on success or false on failure
     */

    public static function updateFile () {
        $med_size = self::getMedSize();
        $values = db::prepareToPost();
        
        if (!empty($_FILES['file']['name']) ){
            $options['filename'] = 'file';
            $options['maxsize'] = self::$maxsize;
            $options['allow_mime'] = self::$allowMime;

            // get fp - will also check for error in upload
            $fp = blob::getFP('file', $options);
            if (!$fp) {
                self::$errors = blob::$errors;
                return false;
            } 
            
            $values['file_org'] = $fp;
            
            if (empty($med_size)) {
                $med_size = conf::getModuleIni('image_scale_width');
            }

            self::scaleImage(
                    $_FILES['file']['tmp_name'], 
                    $_FILES['file']['tmp_name'] . "-med", 
                    $med_size);
            $fp_med = fopen($_FILES['file']['tmp_name'] . "-med", 'rb');
            $values['file'] = $fp_med;

            self::scaleImage(
                    $_FILES['file']['tmp_name'], 
                    $_FILES['file']['tmp_name'] . "-thumb", 
                    conf::getModuleIni('image_scale_width_thumb'));
            $fp_thumb = fopen($_FILES['file']['tmp_name'] . "-thumb", 'rb'); 
        
            $values['file_thumb'] = $fp_thumb;

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
    
    /**
     * display a insert file form
     * @param type $options
     */
    public static function viewFileFormInsertClean($options) {

        if (isset($options['redirect'])) {
            $redirect = $options['redirect'];
        } else {
            $redirect = '#!';
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
    
    /**
     * view form for uploading a file.
     * @param type $options
     */
    public static function viewFileFormInsert($options){
        if (conf::getModuleIni('image_redirect_parent')) {
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
        $search = array ('parent_id =' => $parent, 'reference =' => $reference);
        $res = q::delete(self::$fileTable)->filterArray($search)->exec();
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

    public static function viewFileFormUpdate(){
        $redirect = moduleloader::buildReferenceURL('/image/add', self::$options);
        if (isset($_POST['submit'])){
            self::validateInsert('update');
            if (!isset(self::$errors)){
                $res = self::updateFile();
                if ($res){
                    session::setActionMessage(lang::translate('Image was updated'));
                    http::locationHeader($redirect);
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

        self::init();
        self::setFileId($frag = 2);
        
        $size = self::getImageSize(); 
        $file = self::getFile($size);
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

class image_module extends image {}
