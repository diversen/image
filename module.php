<?php

namespace modules\image;

use diversen\conf;
use diversen\db;
use diversen\db\q;
use diversen\db\admin;
use diversen\html;
use diversen\http;
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
use Exception;
use Gregwar\Image\Image;

/**
 * class content files is used for keeping track of file changes
 * in db. Uses object fileUpload
 *
 * @package image
 */
class module {


    public $errors = null;
    public $status = null;
    public $parent_id;
    public $maxsize = 2000000; // 2 mb max size
    public $options = array();
    public $path = '/image';
    public $fileTable = 'image';
    public $scaleWidth;
    public $allow;
    public $allowMime = 
        array ('image/gif', 'image/jpeg', 'image/pjpeg', 'image/png');

    /**
     * constructor sets init vars
     */
    public function __construct($options = null) {

        moduleloader::includeModule('image');
        $this->options = $options;
        if (!isset($options['allow'])) {
            $this->allow = conf::getModuleIni('image_allow_edit');
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
        $res = q::delete($this->fileTable)->filterArray($search)->exec();
        return $res;
    }

    /**
     * Note: All images are public
     * Expose images in JSON format
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
            $rows[$key]['url_m'] = $this->path . "/download/$val[id]/" . strings::utf8SlugString($val['title']);
            $rows[$key]['url_s'] = $this->path . "/download/$val[id]/" . strings::utf8SlugString($val['title']) . "?size=file_thumb";
            $str = strings::sanitizeUrlRigid(html::specialDecode($val['abstract']));
            $rows[$key]['title'] = $str; 
        }
        
        $images = array ('images' => $rows);
        echo json_encode($images);
        die;
    }
    
    /**
     * get options from QUERY
     * @return array $options
     */
    public function getOptions() {
        $options = array
            ('parent_id' => $_GET['parent_id'],
            'return_url' => $_GET['return_url'],
            'reference' => $_GET['reference'],
            'query' => parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY));
        return $options;
    }
    
    /**
     * check access to module based on options and blog ini settings 
     * @param array $options
     * @return void
     */
    public function checkAccess ($options) {
        
        // Admin user is allowed
        if (session::isAdmin()) {
            return true;
        }
        
        // check access
        if (!session::checkAccessClean($this->allow)) {
            return false;
        }

        // if allow is set to user - this module only allow user to edit his images
        // to references and parent_ids which he owns
        if ($this->allow == 'user') {
            
            $table = moduleloader::moduleReferenceToTable($options['reference']);
            if (!admin::tableExists($table)) {
                return false;
            }
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
    public function setHeadlineTitle ($action = '') {

        $options = $this->getOptions();
        if ($action == 'add') {
            $title = lang::translate('Add images');
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
        template::setTitle($title);
    }
    
    /**
     * add action
     * @return mixed
     */
    public function addAction() {
        
        if (!isset($_GET['parent_id'], $_GET['return_url'], $_GET['reference'] )) { 
            moduleloader::setStatus(403);
            return false;
        }
        
        // get options from QUERY
        $options = $this->getOptions();
        
        if (!$this->checkAccess($options)) {
            moduleloader::setStatus(403);
            return false;
        }

        layout::setMenuFromClassPath($options['reference']);
        $this->setHeadlineTitle('add');

        // display image module content
        $this->init($options);
        $this->viewInsert($options);
        
        // display files
        echo self::displayFiles($options);
    }


    /**
     * delete action
     * @return type
     */
    public function deleteAction() {
        $options = $this->getOptions();
        if (!$this->checkAccess($options)) {
            moduleloader::setStatus(403);
            return;
        }

        layout::setMenuFromClassPath($options['reference']);
        $this->setHeadlineTitle('delete');
        $this->init($options);
        $this->viewDelete();
    }


    /**
     * edit action
     * @return void
     */
    public function editAction() {
        $options = $this->getOptions();
        
        // check access
        if (!$this->checkAccess($options)) {
            moduleloader::setStatus(403);
            return;
        } 
        
        layout::setMenuFromClassPath($options['reference']);
        $this->setHeadlineTitle('edit');

        $this->init($options);
        $this->viewUpdate($options);
    }

    /**
     * download controller
     */
    public function downloadAction() {
        
        $id = uri::fragment(2);
        $size = self::getImageSize(); 
        $file = self::getFile($id);

        if (empty($file)) {
            moduleloader::setStatus(404);
            return;
        }
        
        http::cacheHeaders();
        if (isset($file['mimetype']) && !empty($file['mimetype'])) {
            header("Content-type: $file[mimetype]");
        }
        
        
        if (method_exists('modules\image\config', 'checkAccessDownload')) {
            \modules\image\config::checkAccessDownload($file);
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
        $this->init($options);
        $this->validateInsert();
        if (!isset($this->errors)) {
            $res = self::insertFiles();
            if ($res) {
                echo lang::translate('Image was added');
            } else {
                echo reset($this->errors);
            }
        } else {
            echo reset($this->errors);
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
                    'url' => $this->path . '/admin'));
        
        $per_page = 10;
        $total = q::numRows('image')->fetch();
        $p = new pagination($total);

        $from = @$_GET['from'];
        if (isset($_GET['delete'])) {
            $this->deleteFile($_GET['delete']);    
            http::locationHeader($this->path . "/admin?from=$from", 
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
            echo html::createLink($this->path . "/admin?delete=$row[id]&from=$from", lang::translate('Delete image'));
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
    public function init ($options = null){
        $this->options = $options;
        $this->scaleWidth = conf::getModuleIni('image_scale_width');
        $this->path = '/image';
        $this->fileTable = 'image';
        $this->maxsize = conf::getModuleIni('image_max_size');
  
    }
    
    /**
     * check if image exists
     * @param int $id
     * @param string $reference
     * @return boolean
     */
    public  function imageExists ($id, $reference) {
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
    public  function getImgTag ($row, $size = "file_org", $options = array ()) {
        return $img_tag = html::createHrefImage(
                $this->path . "/download/$row[id]/$row[title]?size=file_org", 
                $this->path . "/download/$row[id]/$row[title]?size=$size", 
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
    public function formUpdate($method, $id = null, $values = array(), $caption = null){
        
        
        $f = new html();
        $f->formStartAry(array('id' => 'image_upload_form', 'onsubmit' => "setFormSubmitting()"));

        $values = $this->getSingleFileInfo($id);
        $f->init($values, 'submit');

        $legend = lang::translate('Edit image');
        $submit = lang::translate('Update');

        $f->legend($legend);

        $bytes = conf::getModuleIni('image_max_size');
        $options = array();
        
        $f->fileWithLabel('files[]', $bytes, $options);
        
        $f->label('abstract', lang::translate('Title'));
        $f->textareaSmall('abstract');

        $fields = $this->formFields();
        if ($fields) {
            if (in_array('figure', $fields)) {
                $f->label('figure', lang::translate('Figure'));
                $f->checkbox('figure');
            }
        }
        
        $f->submit('submit', $submit);
        $f->formEnd();
        return $f->getStr();
    }
    
    public function formFields () {
        $fields = conf::getModuleIni('image_form_fields');
        if ($fields) {
            return explode(',', $fields);
        }
        return false;
    }
    
   /**
    * method for creating a form for insert, update and deleting entries
    * in module_system module
    *
    *
    * @param string    method (update, delete or insert)
    * @param int       id (if delete or update)
    */
    public function formInsert(){
        
        
        $f = new html();
        $f->formStartAry(array('id' => 'image_upload_form', 'onsubmit'=>"setFormSubmitting()"));

        $f->init(html::specialEncode($_POST), 'submit');
        $legend = lang::translate('Add images');
        $submit = lang::translate('Add');

        $f->legend($legend);

        $bytes = conf::getModuleIni('image_max_size');
        $options = $this->options;
        
        if (isset($options['multiple']) && $options['multiple'] == false) {
            unset($options['multiple']);
        } else {
            $options['multiple'] = "multiple";
        }
        
        
        $f->fileWithLabel('files[]', $bytes, $options);        
        $f->label('abstract', lang::translate('Title'));
        $f->textareaSmall('abstract');

        $f->submit('submit', $submit);
        $f->formEnd();
        return $f->getStr();
    }
    
    /**
     * get full web path to a image.
     * @param type $row
     * @param type $size
     * @return string
     */
    public  function getFullWebPath ($row, $size = null) {
        $str = $this->path . "/download/$row[id]/" . strings::utf8SlugString($row['title']);
        return $str;
    }
    
    /**
     * methoding for setting med size if allowed
     */
    public  function getMedSize () {
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
    public function insertFiles () {
        
        $_POST = html::specialDecode($_POST);
        
        $ary = $this->getUploadedFilesArray();
        foreach($ary as $file) {
            $res = $this->insertFile($file);
            if (!$res) {
                return false;
            }
        }
        return true;
    }
    
    public function uploadJs () { ?>
<script>
var formSubmitting = false;
var setFormSubmitting = function() { formSubmitting = true; };

window.onload = function() {
    window.addEventListener("beforeunload", function (e) {
        if (formSubmitting) {
            return undefined;
        }

        var confirmationMessage = 'It looks like you have been editing something. '
                                + 'If you leave before saving, your changes will be lost.';

        (e || window.event).returnValue = confirmationMessage; //Gecko + IE
        return confirmationMessage; //Gecko + Webkit, Safari, Chrome etc.
    });
};
</script>
    <?php }
    
    /**
     * Get uploaded files as a organized array
     * @return array $ary
     */
    public function getUploadedFilesArray () {
                
        $ary = array ();
        foreach ($_FILES['files']['name'] as $key => $name) {
            $ary[$key]['name'] = $name;
        }
        foreach ($_FILES['files']['type'] as $key => $type) {
            $ary[$key]['type'] = $type;
        }
        foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
            $ary[$key]['tmp_name'] = $tmp_name;
        }
        foreach ($_FILES['files']['error'] as $key => $error) {
            $ary[$key]['error'] = $error;
        }
        foreach ($_FILES['files']['size'] as $key => $size) {
            $ary[$key]['size'] = $size;
        }
        return $ary;
    }

    
    public function insertFile ($file) {

        $options = array();
        $options['maxsize'] = $this->maxsize;
        $options['allow_mime'] = $this->allowMime;
        
        // get med size
        $med_size = self::getMedSize();
        
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
        $values['parent_id'] = $this->options['parent_id'];
        $values['reference'] = $this->options['reference'];
        $values['abstract'] = html::specialDecode($_POST['abstract']);
        $values['user_id'] = session::getUserId();
        
        $bind = array(
            'file_org' => PDO::PARAM_LOB, 
            'file' => PDO::PARAM_LOB,
            'file_thumb' => PDO::PARAM_LOB,);
        
        $db = new db();
        $res = $db->insert($this->fileTable, $values, $bind);
        return $res;
    }
    
    /**
     * @param type $image the image file to scale from
     * @param type $thumb the image file to scale to
     * @param type $width the x factor or width of the image
     * @return type 
     */
    public function scaleImage ($image, $thumb, $width){
        try {
            Image::open($image)->cropResize($width)->save($thumb);
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
            return false;
        }
        return true;

    }

    /**
     * validate before insert. No check for e.g. size
     * this is checked but no errors are given. 
     * just check if there is a file. 
     * @param type $mode 
     */
    public function validateInsert($mode = false){
        if ($mode != 'update') {
            if (empty($_FILES['files']['name']['0'])){
                $this->errors[] = lang::translate('No file was specified');
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
        $res = q::delete($this->fileTable)->filter( 'id =', $id)->exec();
        return $res;
    }

    
    /**
     * get admin when operating as a sub module
     * @param array $options
     * @return string  
     */
    public function subModuleAdminOption ($options){
        
        $i = new self();
        $url = $i->path . "/add?" . http_build_query($options);
        $extra = null;
        if (isset($options['options'])) {
            $extra = $options['options'];
        }
        return html::createLink($url, lang::translate('Images'), $extra); 
    }


    /**
     * Displays all files from db rows and options
     * @param array $rows
     * @param array $options
     * @return string $html
     */
    public function displayFiles($options){

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
            $add = $this->path . "/edit/$val[id]?" . $options['query'];
            $str.= MENU_SUB_SEPARATOR_SEC;
            $str.= html::createLink($add, lang::translate('Edit'));
            
            // delete link
            $delete = $this->path . "/delete/$val[id]?" . $options['query'];
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
    public  function getAllFilesInfo($options){
        $db = new db();
        $search = array (
            'parent_id' => $options['parent_id'],
            'reference' => $options['reference']
        );

        $fields = array ('id', 'parent_id', 'title', 'abstract', 'published', 'created');
        $rows = $db->selectAll($this->fileTable, $fields, $search, null, null, 'created', false);
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
    public function getSingleFileInfo($id){

        $db = new db();
        $search = array (
            'id' => $id
        );

        $fields = array ('id', 'parent_id', 'title', 'figure', 'abstract', 'published', 'created', 'reference');
        $row = $db->selectOne($this->fileTable, null, $search, $fields, null, 'created', false);
        return $row;
    }

    /**
     * method for fetching one full file row
     * @return array $row
     */
    public  function getFile($id){
        $db = new db();
        $row = $db->selectOne($this->fileTable, 'id', $id);
        return $row;
    }

    /**
     * method for updating a module in database
     * @return boolean $res true on success or false on failure
     */
    public function updateFile() {

        $id = uri::fragment(2);
        $options = $this->getOptions();

        $med_size = self::getMedSize();
        $values = db::prepareToPostArray(array('abstract', 'figure'));

        

        $options = array();
        $options['maxsize'] = $this->maxsize;
        $options['allow_mime'] = $this->allowMime;
        
        // get med size
        $med_size = self::getMedSize();
        
        $files = $this->getUploadedFilesArray();
        
        if (isset($files[0]['name']) && !empty($files[0]['name'])) {
            $file = $files[0];
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
            $values['parent_id'] = $this->options['parent_id'];
            $values['reference'] = $this->options['reference'];
            $values['abstract'] = html::specialDecode($_POST['abstract']);
            $values['figure'] = $_POST['figure'];
            //die;
            $values['user_id'] = session::getUserId();

            
            $bind = array(
                'file_org' => PDO::PARAM_LOB, 
                'file' => PDO::PARAM_LOB,
                'file_thumb' => PDO::PARAM_LOB,);

        }
        $db = new db();
        $res = $db->update($this->fileTable, $values, $id, $bind);
        return $res;
    }

    /**
     * display a insert file form
     * @param type $options
     */
    public function viewFileFormInsertClean($options) {

        if (isset($options['redirect'])) {
            $redirect = $options['redirect'];
        } else {
            $redirect = '#!';
        }

        if (isset($_POST['submit'])){
            $this->validateInsert();
            if (!isset($this->errors)){
                $res = $this->insertFiles();
                if ($res){
                    session::setActionMessage(lang::translate('Image was added'));
                    http::locationHeader($redirect);
                } else {
                    html::errors($this->errors);
                }
            } else {
                html::errors($this->errors);
            }
        }
        echo $this->formInsert('insert');
    }
    
    /**
     * view form for uploading a file.
     * @param type $options
     */
    public function viewInsert($options){

        $redirect = $options['return_url'];
        if (isset($_POST['submit'])){
            
            $this->validateInsert();
            
            if (!isset($this->errors)){
                $res = $this->insertFiles();
                if ($res){
                    session::setActionMessage(lang::translate('Image was added'));
                    $this->redirectImageMain($options);
                } else {
                    echo html::getErrors($this->errors);
                }
            } else {
                echo html::getErrors($this->errors);
            }
        }
        echo $this->formInsert('insert');
    }

    /**
     * view form for deleting image
     */
    public function viewDelete(){
        
        $id = uri::fragment(2);
        $options = $this->getOptions();
        if (isset($_POST['submit'])){
            if (!isset($this->errors)){
                $res = self::deleteFile($id);
                if ($res){
                    session::setActionMessage(lang::translate('Image was deleted'));
                    $this->redirectImageMain($options);
                }
            } else {
                echo html::getErrors($this->errors);
            }
        }
        echo $this->formDelete();
    }
    
    public function formDelete () {
        $f = new html();
        $f->formStartAry(array('id' => 'image_upload_form', 'onsubmit' => "setFormSubmitting()"));
        $legend = lang::translate('Delete image');
        $f->legend($legend);
        $f->submit('submit', lang::translate('Delete'));
        return $f->getStr();
    }
    
    /**
     * Redirect to main action 
     * @param array $options
     */
    public function redirectImageMain ($options) {
        $url = "/image/add/?$options[query]";
        http::locationHeader($url);
        
    }

    /**
     * view form for updating an image
     */
    public function viewUpdate($options){
        $id = uri::fragment(2);
        if (isset($_POST['submit'])){
            
            $this->validateInsert('update');
            if (!isset($this->errors)){
                $res = $this->updateFile();
                if ($res){
                    session::setActionMessage(lang::translate('Image was updated'));
                    $this->redirectImageMain($options);
                } else {
                    echo html::getErrors($this->errors);
                }
            } else {
                echo html::getErrors($this->errors);
            }
        }
        echo $this->formUpdate('update', $id);
    }

    /**
     * get a size of image to deliver based on $_GET['size']
     * @return string
     */
    public  function getImageSize () {
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
