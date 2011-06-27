<?php

class filesController extends controller{
    public function header(){
        echo $this->head();
        echo '<body>';
        $bc = yBreadcrumbSet::getInstance();
        echo '<div style="padding: 30px;">'.$bc;
    }
    public function footer(){
        echo '</div>';
        echo '</body>';
        echo '</html>';
    }
    public function onConstruct(){
        $this->requireJs('/js/jquery-1.5.min.js');
        $this->requireJs('/js/jquery-ui-1.8.13.min.js');
        //$this->requireJs('/js/files.js');
        $this->requireCss('/css/reset.css');
        $this->requireCss('/css/typography.css');
        $this->requireCss('/js/filemanager/filemanager.css');
        $this->setTitle('Files Demo');
        //$this->appendToBreadcrumb('<a href="/">Files</a>');
        $this->requireJs(app()->rel('js/filemanager/filemanager.js'));
        $this->css('
		.upload-form, .upload-form span, .upload-form input{
			font-size: 12px;
			vertical-align: middle;
		}
		.upload-form{
			line-height: 20px;
			padding: 5px 5px;
		}
		body.wait * {
			cursor: wait !important;
		}
		');
        $this->js('
		$(function(){
			$(".files-list").explorer({
                api: "/api/"
            });
		});
		');
        $this->appendToBreadcrumb('<a href="'.$this->rel().'">Файлы</a>');
    }
    protected function _apiError($code){
        $obj = new stdClass();
        $obj->status = $code;
        echo json_encode($obj);
        exit;
    }
    /**
     * !RouteInit ANY api
     */
    public function api(){
        if (!request::isAjax()){
            $this->_apiError(1);
        }
        if (isset($_POST['action'])){
            switch ($_POST['action']){
                case 'list':
                    $id = $_POST['id'];
                    if (isset($_POST['type']) && $_POST['type'] === 'parent'){
                        if (($folder = fileFolderModel::get($id))){
                            $id = strval($folder->parentId);
                        }else{
                            $this->_apiError(4);
                        }
                    }
                    $obj = new stdClass();
                    $obj->status = 0;
                    $obj->id = strval($id);
                    $obj->result = fileFolderModel::getList($id);
                    echo json_encode($obj);
                    exit;
                    break;
                case 'rename':
                    if (isset($_POST['name'])){
                        if ($_POST['type'] == 'file'){
                            if (($file = fileModel::get($_POST['id']))){
                                $file->name = $_POST['name'];
                                $file->save();
                                $this->_apiError(0);
                            }else{
                                $this->_apiError(4); // 404 :)
                            }
                        }
                        if ($_POST['type'] == 'folder'){
                            if (($folder = fileFolderModel::get($_POST['id']))){
                                $folder->name = $_POST['name'];
                                $folder->save();
                                $this->_apiError(0);
                            }else{
                                $this->_apiError(4); // 404 :)
                            }
                        }
                    }
                    $this->_apiError(1); // incorrect request
                    break;
                case 'delete':
                    if ($_POST['type'] == 'file'){
                        if (($file = fileModel::get($_POST['id']))){
                            $file->name = $_POST['name'];
                            $file->delete();
                            $this->_apiError(0);
                        }else{
                            $this->_apiError(4); // 404 :)
                        }
                    }
                    if ($_POST['type'] == 'folder'){
                        if (($folder = fileFolderModel::get($_POST['id']))){
                            $folder->name = $_POST['name'];
                            $folder->delete();
                            $this->_apiError(0);
                        }else{
                            $this->_apiError(4); // 404 :)
                        }
                    }
                    $this->_apiError(1); // incorrect request
                    break;
                case 'create':
                    if ($_POST['type'] == 'folder'){
                        $folder = new fileFolderModel();
                        $folder->name = 'Новая папка';
                        $folder->save();
                        $obj = new stdClass();
                        $obj->status = 0;
                        $obj->result = $folder->toObject();
                        echo json_encode($obj);
                        exit;
                    }
                    $this->_apiError(1); // incorrect request
                    break;
                case 'move':
                    break;
            }
        }
        if (isset($_SERVER['HTTP_X_FILE_NAME'])){
            $name = $_SERVER['HTTP_X_FILE_NAME'];
            $targetId = $_SERVER['HTTP_X_TARGET_ID'];
            $tmpname = tempnam(sys_get_temp_dir(), 'upload_');
            $stream = fopen('php://input', 'r');
            file_put_contents($tmpname, $stream);
            $this->upload($tmpname, $name, $targetId);
            $this->_apiError(0);
        }
        $this->_apiError(1);
        //echo json_encode(fileFolderModel::getList(''));
    }
    /**
     * !RouteInit ANY api/clipboard
     */
    public function apiClipboard(){
        if (count($_POST)){
            $_SESSION['files/api/clipboard'] = $_POST;
        }
        echo json_encode($_SESSION['files/api/clipboard']);
    }
    /**
     * !RouteInit ANY api/upload
     */
    public function apiUpload($folderId = ''){
        if (isset($_SERVER['HTTP_X_FILE_NAME'])){
            $name = $_SERVER['HTTP_X_FILE_NAME'];
            //$content = file_get_contents("php://input");
            $tmpname = tempnam(sys_get_temp_dir(), 'upload_');
            $stream = fopen('php://input', 'r');
            file_put_contents($tmpname, $stream);
            $this->upload($tmpname, $name, $folderId);
            $content = file_get_contents($tmpname);
            //var_dump($content);
        }
    }
    /**
     * !RouteInit ANY api/createFolder
     */
    public function apiCreateFolder($folderId = ''){
        $folder = new fileFolderModel();
        $folder->parentId = $folderId;
        $folder->save();
    }
    /**
     * !RouteInit ANY api/listHtml
     */
    public function apiListHtml($folderId = ''){
        $this->viewFolder($folderId, false, true);
    }
    /**
     * !RouteInit ANY api/file/$id/rename
     */
    public function apiFileRename($id = ''){
        if (($file = fileModel::get($id))){
            if (isset($_POST['name'])){
                $file->name = $_POST['name'];
                $file->save();
            }
        }
    }
    /**
     * !RouteInit ANY api/folder/$id/rename
     */
    public function apiFolderRename($id = ''){
        if (($folder = fileFolderModel::get($id))){
            if (isset($_POST['name'])){
                $folder->name = $_POST['name'];
                $folder->save();
            }
        }
    }
    /**
     * !RouteInit ANY api/file/$id/delete
     */
    public function apiFileDelete($id = ''){
        if (($file = fileModel::get($id))){
            $file->delete();
        }
    }
    /**
     * !RouteInit ANY api/file/$id/moveTo/$folderId
     */
    public function apiFileMove($id = '', $folderId = ''){
        if (($file = fileModel::get($id)) && ($folder = fileFolderModel::get($folderId))){
            $file->moveToFolder($folderId);
        }else{
            echo 'false';
        }
    }
    /**
     * !RouteInit ANY api/folder/$id/moveTo/$folderId
     */
    public function apiFileFolderMove($id = '', $folderId = ''){
        if (($folder = fileFolderModel::get($id)) && ($targetFolder = fileFolderModel::get($folderId))){
            $folder->moveToFolder($folderId);
        }else{
            echo 'false';
        }
    }
    /**
     * !RouteInit ANY api/folder/$id/delete
     */
    public function apiFolderDelete($id = ''){
        if (($folder = fileFolderModel::get($id))){
            if ($folder->isEmpty()){
                $folder->delete();
            }
        }
    }
    public function getExt($name){
        $ext = (strpos($name, '.') === false?'dat':end(explode('.', $name)));
        $ext = preg_replace("#[^0-9a-z]+#ims", '', $ext);
        if ($ext == ''){
            $ext = 'dat';
        }
        return $ext;
    }
    public function _header(){
        if (request::isAjax()){
            
        }else{
            parent::_header();
        }
    }
    public function _footer(){
        if (request::isAjax()){
            
        }else{
            parent::_footer();
        }
    }
    public function getUniqueFile($uuid, $ext){

        $hex = str_replace(array('-', '{', '}', '(', ')'), '', $uuid);
        $d1 = substr($hex, 0, 1);
        $d2 = substr($hex, 1, 1);
        $base36 = yMath::baseConvert($hex, 16, 36);
        //echo 'hex: '.$hex.' ';
        //echo 'base36: '.$base36.' '; // a-z0-9
        //echo 'ext: '.$ext.' ';
        $fname = $base36.'.'.$ext;
        $vfs = yVirtualFileSystem::getInstance();
        $files = $vfs->getResource('files::');
        $d = $files->getResource($d1);
        $d->mkDir();
        $d = $d->getResource($d2);
        $d->mkDir();
        $f = $d->getResource($fname);
        //echo $f;
        //echo 'fname: '.$fname.' ';
        return $f;
    }
    public function upload($tmp, $name, $folderId = ''){
        $file = new fileModel();
        $file->folderId = $folderId;
        $file->name = $name;
        $file->size = filesize($tmp);
        $file->id = strval(uuid::v4());
        $file->ext = $this->getExt($name);
        $file->save();
        $file->upload($tmp);
    }
    public function initUpload($return = false, $upload = false, $select = false){
        if ($upload){
            if (isset($_FILES['file'])){
                $f = $_FILES['file'];
                $this->upload($f['tmp_name'], $f['name']);
            }
        }
        if ($select){
            if (isset($_POST['files']) && is_array($_POST['files'])){
                if ($return){
                    response::seeOther($return.'?'.http_build_query(array('files'=>$_POST['files'])));
                }
                //exit;
            }
        }
        $this->back();
    }
    public function showUpload(){
        echo 'up';
    }
    public function actionX(){
        echo 'x';
    }
    public function viewFolder($parentFolderId = '', $filter = false, $onlyItems = false){
        $knownExt = array('pdf', 'txt', 'html', 'swf', 'php', 'exe');
        $imageExt = array('jpg', 'png', 'gif', 'jpeg');
        $files = modelCollection::getInstance('fileModel');
        $folders = modelCollection::getInstance('fileFolderModel');
        $list = $files->select($files->folderId->is($parentFolderId));
        $folderList = $folders->select(
                        $folders->parentId->is($parentFolderId), $folders->isDeleted->is(0)
        );
        //$list->delete();
        if ($filter && ($filter == 'flash' || $filter == 'media')){
            $list->where($files->ext->is('swf'));
        }
        if ($filter && ($filter == 'images' || $filter == 'image')){
            $list->where($files->ext->in($imageExt));
        }
        if ($filter && ($filter == 'icon' )){
            $list->where($files->ext->in(array('ico')));
        }
        if (!$onlyItems){
            echo '<ul class="files-list" id="'.$parentFolderId.'">';
        }
        foreach ($folderList as $folder){
            $img = '/css/images/ext/.thumb/tmm64x64_folder.png';
            echo '<li class="folder" id="'.$folder->id.'" title="size: '.$folder->size.'">';
            /* if ($select){
              echo '<div style="position: absolute; top: 0; left: 0;"><input type="checkbox" name="files[]" value="'.$folder->id.'"></div>';
              } */
            echo '<a rel="" href="'.$folder->getHref().'">';
            echo '<div class="icon">';
            echo '<img src="'.$img.'" />';
            echo '</div>';
            echo '<div class="caption">'.$folder->name->html().'</div>';
            echo '</a>';
            echo '</li>';
        }
        foreach ($list as $file){
            $img = 'file';
            $fancybox = false;
            if (in_array(strval($file->ext), $knownExt)){
                $img = strval($file->ext);
            }
            $img = '/css/images/ext/.thumb/tmm64x64_'.$img.'.png';
            if (in_array(strval($file->ext), $imageExt)){
                $img = $file->getThumbnail(64, 64, 'fit')->getUri();
                $fancybox = true;
            }
            echo '<li id="'.$file->id.'" title="size: '.$file->size.'">';
            if ($select){
                echo '<div style="position: absolute; top: 0; left: 0;"><input type="checkbox" name="files[]" value="'.$file->id.'"></div>';
            }
            echo '<a rel="" target="_blank" href="'.$file->getHref().'">';
            echo '<div class="icon">';
            echo '<img src="'.$img.'" />';
            echo '</div>';
            echo '<div class="caption">'.$file->name->html().'</div>';
            echo '</a>';
            echo '</li>';
        }
        if (!$onlyItems){
            echo '</ul>';
        }
    }
    /**
     * !RouteInit ANY folder/$id
     */
    public function folderRouteInit($id, $up = false){
        if (($folder = fileFolderModel::get($id))){
            $a = array();
            $parent = $folder;
            while ($parent){
                $a[] = array('<a href="'.$parent->getHref().'">'.$parent->name->html().'</a>');
                $parent = $parent->getParent();
                if ($up && $parent){
                    //if ($parent->id->getValue() == ''){
                    response::redirect($this->rel('folder/'.$parent->id));
                    //}
                }elseif ($up){
                    response::redirect($this->rel());
                }
            }
            $this->appendToBreadcrumb(array_reverse($a));
        }
    }
    /**
     * !Route ANY folder/$id
     */
    public function folder($id){
        if (($folder = fileFolderModel::get($id))){
            $this->viewFolder($id, $filter);
        }
    }
    public function _initIndex($parent = false){
        if ($parent){
            
        }
    }
    public function index($filter = false, $return = false){
        //$vfs = yVirtualFileSystem::getInstance();
        //$files = $vfs->getResource('files::');
        //echo $files;
        $select = false;
        $upload = true;
        $fixedSize = false;
        $dialog = isset($_GET['dialog']);
        $tinymce = isset($_GET['tinymce']);
        if ($dialog){
            $select = true;
            $fixedSize = true;
        }
        $dec = !$tinymce && $dialog;

        /* if ($tinymce){
          echo '<script type="text/javascript" src="/js/tiny_mce/tiny_mce_popup.js"></script>';
          echo '<script type="text/javascript">
          $(function(){
          $(".files-list a").bind("click", function(e){
          e.preventDefault();

          var URL = $(this).attr("href");
          //alert(URL);
          tinymceSubmitUrl(URL);
          return false;
          });
          });

          var FileBrowserDialogue = {
          init : function () {
          // Here goes your code for setting your custom things onLoad.
          alert("init");
          },
          mySubmit : function () {
          // Here goes your code to insert the retrieved URL value into the original dialogue window.
          // For example code see below.
          alert("submit");
          }
          }

          //tinyMCEPopup.onInit.add(FileBrowserDialogue.init, FileBrowserDialogue);
          </script>';
          } */
        if (!request::isAjax() && $dec){
            echo '<div class="dialogbox">';
            echo '<div style="background: #fff;">';
        }
        echo '<form method="POST"'.($dialog?' class="ajax"':'').' action="'.$this->rel('upload').($return?'?return='.$return:'').'" enctype="multipart/form-data">';
        echo '<style type="text/css">';
        echo '
		.dialogbox .left-menu a{
			display: block;
			color: #333;
			padding: 3px 10px;
			text-decoration: none;
			font-weight: bold;
		}
		.dialogbox .left-menu a:hover{
			background: #C3D9FF;
		}
		.dialogbox .left-menu a.active{
			background: #6694E3;
			color: #fff;
		}
		
		';
        echo '</style>';
        if ($dec){
            echo '<h1>Выбор файла';
            if (request::isAjax() || $dialog){
                echo '<a href="#" class="close"><img src="/css/images/cross-white-small.png" alt="закрыть" title="Закрыть окно" /></a>';
            }
            echo '</h1>';
        }
        echo '<table';
        if ($fixedSize){
            echo ' width="800" height="480"';
        }else{
            echo ' width="100%"';
        }
        echo ' style="">'; //border-left: solid 1px #333;border-right: solid 1px #333;
        echo '<tr>';
        /* echo '<td width="130" rowspan="2" style="border-right: solid 1px #C2CCCF;">';
          echo '<ul class="left-menu">';
          echo '<li><a href="#">Все файлы</a></li>';
          echo '<li><a href="#">Из галереи</a></li>';
          echo '<li><a href="#">Баннер</a></li>';
          echo '<li><a href="#">Видео</a></li>';
          echo '<li><a href="#">Документы</a></li>';
          echo '</ul>';
          echo '</td>'; */
        echo '<td style="">';
        echo '<div style="height: 435px;overflow-y: auto;padding: 4px;">'; // border: solid 1px #f00;
        $this->viewFolder('', $filter);
        echo '</div>';
        echo '</td>';
        /* echo '<td style="width: 200px; background: url(\'/css/images/checker-16x16.png\') top left repeat;">';
          echo '</td>'; */
        echo '</tr>';
        echo '<tr>';
        echo '<td colspan="2" style="background: #DDE8EF;height: 32px; border-top: solid 1px #C2CCCF;">';
        echo '<div class="upload-form">';
        echo '<span>Загрузить с компьютера:</span> ';
        echo '<input type="file" name="file" />';
        echo '<input class="button" type="submit" name="upload" value="Загрузить" />';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        if ($select){
            echo '<div class="footer">';
            echo '<input type="submit" name="select" class="submit" value="Выбрать" />';
            if ($dialog){
                echo '<a href="'.$this->rel().'" class="close">Отменить</a>';
            }
            echo '</div>';
        }
        echo '</form>';
        if (!request::isAjax() && $dec){
            echo '</div>';
            echo '</div>';
        }
    }
}
