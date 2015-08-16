<?php

namespace modules\image;

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
use PDO;

/**
 * class content files is used for keeping track of file changes
 * in db. Uses object fileUpload
 *
 * @package image
 */
class module {


    public static $errors = null;
    public static $status = null;
    public static $parent_id;
    public static $maxsize = 2000000; // 2 mb max size
    public static $options = array();
    public static $path = '/image';
    public static $fileTable = 'image';
    public static $scaleWidth;
    public static $allow;
    public static $allowMime = 
        array ('image/gif', 'image/jpeg', 'image/pjpeg', 'image/png');

    /**
     *
     * constructor sets init vars
     */
    function __construct($options = null) {
        
        // we include module in order to insure that ini settings are loaded.
        // these needs to be loaded when using the module as a submodule.
        moduleloader::includeModule('image');
        self::$options = $options;
        if (!isset($options['allow'])) {
            self::$allow = conf::getModuleIni('image_allow_edit');
        }
    }

    /**
     * delete all images based on parent and reference
     * @param int $parent
     * @param string $reference
     * @return boolean $res
     */
    public function deleteAll($parent, $reference) {
        $search = array('parent_id =' => $parent, 'reference =' => $reference);
        $res = q::delete(self::$fileTable)->filterArray($search)->exec();
        return $res;
    }

    /**
     * expose images in json format
     * @return type
     */
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
            $rows[$key]['url_m'] = self::$path . "/download/$val[id]/" . strings::utf8SlugString($val['title']);
            $rows[$key]['url_s'] = self::$path . "/download/$val[id]/" . strings::utf8SlugString($val['title']) . "?size=file_thumb";
            $str = strings::sanitizeUrlRigid(html::specialDecode($val['abstract']));
            $rows[$key]['title'] = $str; 
        }
        
        $photos = array ('images' => $rows);
        echo json_encode($photos);
        die;
    }
    
    /**
     * get options from QUERY
     * @return array $options
     */
    public static function getOptions() {
        $options = array
            ('parent_id' => $_GET['parent_id'],
            'return_url' => $_GET['return_url'],
            'reference' => $_GET['reference'],
            'query' => $_SERVER['QUERY_STRING']);
        return $options;
    }
    
    /**
     * check access to module based on options and blog ini settings 
     * @param array $options
     * @return void
     */
    public static function checkAccess ($options) {
        
        // check access
        if (!session::checkAccessClean(self::$allow)) {
            return false;
        }

        // if allow is set to user - this module only allow user to edit his images
        // to references and parent_ids which he owns
        if (self::$allow == 'user') {
            $table = moduleloader::moduleReferenceToTable($options['reference']);
            if (!user::ownID($table, $options['parent_id'], session::getUserId())) {
                moduleloader::setStatus(403);
                return false;
            }
        }
        return true;
    }
    
    /**
     * set a headline and page title based on action
     * @param string $action 'add', 'edit', 'delete'
     */
    public static function setHeadlineTitle ($action = '') {

        $options = self::getOptions();
        if ($action == 'add') {
            $title = lang::translate('Add image');
        }
        
        if ($action == 'edit') {
            $title = lang::translate('Edit image');
        }
        
        if ($action == 'delete') {
            $title = lang::translate('Delete image');
        }
            
        // set headline and title
        $headline = $title . MENU_SUB_SEPARATOR_SEC;
        $headline.= html::createLink($options['return_url'], lang::translate('Go back'));

        echo html::getHeadline($headline);
        template::setTitle(lang::translate($title));
    }
    
    /**
     * add action
     * @return mixed
     */
    public function addAction() {
        
        // get options from QUERY
        $options = self::getOptions();
        
        if (!self::checkAccess($options)) {
            moduleloader::setStatus(403);
            return false;
        }

        layout::setMenuFromClassPath($options['reference']);
        self::setHeadlineTitle('add');

        // display image module content
        self::init($options);
        self::viewFileFormInsert($options);
        
        // display files
        echo self::displayFiles($options);
    }


    /**
     * delete action
     * @return type
     */
    public function deleteAction() {
        $options = self::getOptions();
        if (!self::checkAccess($options)) {
            moduleloader::setStatus(403);
            return;
        }

        self::setHeadlineTitle('delete');
        self::init($options);
        self::viewFileFormDelete();
    }


    /**
     * edit action
     * @return void
     */
    public function editAction() {
        $options = self::getOptions();
        
        // check access
        if (!self::checkAccess($options)) {
            moduleloader::setStatus(403);
            return;
        } 
        
        self::setHeadlineTitle('edit');

        self::init($options);
        self::viewFileFormUpdate($options);
    }

    /**
     * download controller
     */
    public function downloadAction() {
        
        $id = uri::fragment(2);
        //die;
        $size = self::getImageSize(); 
        $file = self::getFile($id);
        //print_r($file); die;
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
                    'url' => self::$path . '/admin'));
        
        $per_page = 10;
        $total = q::numRows('image')->fetch();
        $p = new pagination($total);

        $from = @$_GET['from'];
        if (isset($_GET['delete'])) {
            $this->deleteFile($_GET['delete']);    
            http::locationHeader(self::$path . "/admin?from=$from", 
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
            echo html::createLink(self::$path . "/admin?delete=$row[id]&from=$from", lang::translate('Delete image'));
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
     * get a image html tag
     * @param array $row
     * @param string $size
     * @param array $options
     * @return string $html image tag
     */
    public static function getImgTag ($row, $size = "file_org", $options = array ()) {
        return $img_tag = html::createHrefImage(
                self::$path . "/download/$row[id]/$row[title]?size=file_org", 
                self::$path . "/download/$row[id]/$row[title]?size=$size", 
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
        
        $legend = '';
        
        // update
        if (isset($id)) {
            $values = self::getSingleFileInfo($id);
            $h->init($values, 'submit'); 
            
            $legend = lang::translate('Edit image');
            $submit = lang::translate('Update');
        } else {
            $h->init(html::specialEncode($_POST), 'submit'); 
            $legend = lang::translate('Add image');
            $submit = lang::translate('Add');
            
            
            
        }
        
        $h->legend($legend);


        if (conf::getModuleIni('image_user_set_scale')) {
            $h->label('scale_size', lang::translate('Image width in pixels, e.g. 100'));
            $h->text('scale_size');
        }

        $bytes = conf::getModuleIni('image_max_size');
        $h->fileWithLabel('file', $bytes);

        $h->label('abstract', lang::translate('Abstract'));
        $h->textareaSmall('abstract');

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
        $str = self::$path . "/download/$row[id]/" . strings::utf8SlugString($row['title']);
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
        
        $options = array();
        $options['filename'] = $input;
        $options['maxsize'] = self::$maxsize;
        $options['allow_mime'] = self::$allowMime;
        
        // get med size
        $med_size = self::getMedSize();
        
        // get fp - will also check for error in upload
        $fp = blob::getFP('file', $options);
        if (!$fp) {
            self::$errors = blob::$errors;
            return false;
        } 
        
        $values['file_org'] = $fp;
        
        // we got a valid file pointer checked for errors
        // now we use the tmp file when scaleing. Only
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
     * validate before insert. No check for e.g. size
     * this is checked but no errors are given. 
     * just check if there is a file. 
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
        $url = self::$path . "/add?" . http_build_query($options);
        $extra = null;
        if (isset($options['options'])) {
            $extra = $options['options'];
        }
        return html::createLink($url, lang::translate('Add image'), $extra); 
    }


    /**
     * displays all files from db rows and options
     * @param array $rows
     * @param array $options
     * @return string $html
     */
    public static function displayFiles($options){

        
        // get info about all images
        $rows = self::getAllFilesInfo($options);
        
        // create string with HTML
        $str = "";
        foreach ($rows as $val){
            
            // generate title
            $title = lang::translate('Download');
            $title.= MENU_SUB_SEPARATOR_SEC;
            $title.= htmlspecialchars($val['title']);
            
            // create link to image
            $link_options = array('title' => htmlspecialchars($val['abstract']));
            $str.= html::createLink($val['image_url'], $title, $link_options);

            // edit link
            $add = self::$path . "/edit/$val[id]?" . $options['query'];
            $str.= MENU_SUB_SEPARATOR_SEC;
            $str.= html::createLink($add, lang::translate('Edit'));
            
            // delete link
            $delete = self::$path . "/delete/$val[id]?" . $options['query'];
            $str.= MENU_SUB_SEPARATOR;
            $str.= html::createLink($delete, lang::translate('Delete'));

            // break
            $str.= "<br />\n";
        }
        echo $str;
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
    public static function getSingleFileInfo($id){

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
    public static function getFile($id){
        $db = new db();

        
        //$db->selectOne(self::$fileTable, 'id', $id, array($size));
        $row = $db->selectOne(self::$fileTable, 'id', $id);
        return $row;
    }

    /**
     * method for updating a module in database
     * @return boolean $res true on success or false on failure
     */
    public static function updateFile() {

        $id = uri::fragment(2);
        $options = self::getOptions();

        $med_size = self::getMedSize();
        $values = db::prepareToPost();


        if (!empty($_FILES['file']['name'])) {
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
                    $_FILES['file']['tmp_name'], $_FILES['file']['tmp_name'] . "-med", $med_size);
            $fp_med = fopen($_FILES['file']['tmp_name'] . "-med", 'rb');
            $values['file'] = $fp_med;

            self::scaleImage(
                    $_FILES['file']['tmp_name'], $_FILES['file']['tmp_name'] . "-thumb", conf::getModuleIni('image_scale_width_thumb'));
            $fp_thumb = fopen($_FILES['file']['tmp_name'] . "-thumb", 'rb');

            $values['file_thumb'] = $fp_thumb;

            $values['title'] = $_FILES['file']['name'];
            $values['mimetype'] = $_FILES['file']['type'];
            $values['parent_id'] = $options['parent_id'];
            $values['reference'] = $options['reference'];

            $bind = array(
                'file_org' => PDO::PARAM_LOB,
                'file' => PDO::PARAM_LOB,
                'file_thumb' => PDO::PARAM_LOB,);
        }
        $db = new db();
        $res = $db->update(self::$fileTable, $values, $id, $bind);
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

        $redirect = $options['return_url'];
        if (isset($_POST['submit'])){
            self::validateInsert();
            if (!isset(self::$errors)){
                $res = self::insertFile();
                if ($res){
                    session::setActionMessage(lang::translate('Image was added'));
                    self::redirectImageMain($options);
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
     * view form for deleting image
     */
    public static function viewFileFormDelete(){
        
        $id = uri::fragment(2);
        $options = self::getOptions();
        if (isset($_POST['submit'])){
            if (!isset(self::$errors)){
                $res = self::deleteFile($id);
                if ($res){
                    session::setActionMessage(lang::translate('Image was deleted'));
                    self::redirectImageMain($options);
                }
            } else {
                html::errors(self::$errors);
            }
        }
        self::viewFileForm('delete', $id);
    }
    
    public static function redirectImageMain ($options) {
        $url = "/image/add/?$options[query]";
        http::locationHeader($url);
        
    }

    /**
     * view form for updating an image
     */
    public static function viewFileFormUpdate($options){
        $id = uri::fragment(2);
        if (isset($_POST['submit'])){
            self::validateInsert('update');
            if (!isset(self::$errors)){
                $res = self::updateFile();
                if ($res){
                    session::setActionMessage(lang::translate('Image was updated'));
                    self::redirectImageMain($options);
                } else {
                    html::errors(self::$errors);
                }
            } else {
                html::errors(self::$errors);
            }
        }
        self::viewFileForm('update', $id);
    }

    /**
     * get a size of image to deliver based on $_GET['size']
     * @return string
     */
    public static function getImageSize () {
        $size = null;
        if (!isset($_GET['size'])) {
            $size = 'file';
        } else {
            $size = $_GET['size'];
        }
        if ($size != 'file' && $size != 'file_thumb' && $size != 'file_org') {
            $size = 'file';
        }
        return $size;
    }
}
