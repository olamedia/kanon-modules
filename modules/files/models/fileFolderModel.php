<?php

class fileFolderModel extends model{
    protected $_properties = array(
        'id'=>array(// UUID
            'class'=>'uuidProperty',
            'field'=>'uuid',
            'primaryKey'=>true,
        ),
        'parentId'=>array(
            'class'=>'stringProperty', // uuid is automatic
            'field'=>'parent_id',
            'parentKey'=>true,
        ),
        'siteId'=>array(
            'class'=>'stringProperty',
            'field'=>'site_id'
        ),
        'size'=>array(
            'class'=>'integerProperty',
            'field'=>'size'
        ),
        'name'=>array(
            'class'=>'stringProperty',
            'field'=>'name'
        ),
        'isDeleted'=>array(// recycle bin
            'class'=>'booleanProperty',
            'field'=>'is_deleted'
        ),
    );
    protected $_actAs = array('timestampable');
    public function toObject(){
        $obj = new stdClass();
        $obj->type = 'folder';
        $obj->id = strval($this->id);
        $parentId = strval($this->parentId);
        if ($parentId != ''){
            $obj->parentId = $parentId;
        }
        $obj->name = strval($this->name);
        $obj->icon = '/css/images/ext/.thumb/tmm64x64_folder.png';
        return $obj;
    }
    public static function getList($parentId){
        $list = array();
        $folders = modelCollection::getInstance('fileFolderModel');
        $files = modelCollection::getInstance('fileModel');
        $foldersList = $folders->select($folders->parentId->is($parentId), $folders->isDeleted->is(0));
        foreach ($foldersList as $folder){
            $list[] = $folder->toObject();
        }
        $filesList = $files->select($files->folderId->is($parentId));
        foreach ($filesList as $file){
            $list[] = $file->toObject();
        }
        return $list;
    }
    public function toListObject(){
        $obj = $this->toObject();
        $obj->{'list'} = self::getList($this->id);
        return $obj;
    }
    public function isEmpty(){
        $folders = modelCollection::getInstance('fileFolderModel');
        $files = modelCollection::getInstance('fileModel');
        return!(
                count($folders->select($folders->parentId->is($this->id), $folders->isDeleted->is(0))) +
                count($files->select($files->folderId->is($this->id))))
        ;
    }
    public function moveToFolder($folderId){
        $sourceFolder = fileFolderModel::get($this->parentId);
        if ($targetFolder = fileFolderModel::get($folderId)){
            $this->parentId = $targetFolder->id;
            $this->save();
            $targetFolder->updateSize();
            if ($sourceFolder){
                $sourceFolder->updateSize();
            }
            return true;
        }
        return false;
    }
    public function preInsert(){
        $this->id = strval(uuid::v4());
    }
    public function preSave(){
        $changed = false;
        if ($this->name->getValue() == ''){
            $this->name = 'Новая папка';
            $changed = true;
        }
        $folders = modelCollection::getInstance('fileFolderModel');
        $i = 1;
        $name = $this->name->getValue();
        while ($folders->select(
                $folders->id->not($this->id), $folders->isDeleted->is(0), $folders->parentId->is($this->parentId), $folders->name->is($this->name)
        )->fetch()){
            $i++;
            $this->name = $name.' ('.$i.')';
            $changed = true;
        }
    }
    public function delete($useBin = true){
        if (!$useBin){
            return parent::delete();
        }
        $this->isDeleted = 1;
        $this->save();
    }
    public function preDelete(){
        // on real delete
        $folders = modelCollection::getInstance('fileFolderModel');
        $files = modelCollection::getInstance('fileModel');
        $folders->select($folders->parentId->is($this->id))->delete();
        $files->select($files->folderId->is($this->id))->delete();
    }
    public function updateSize(){
        $files = modelCollection::getInstance('fileModel');
        $sum = $files->select($files->folderId->is($this->id), $files->size->sum())->raw()->fetch();
        $this->size = $sum;
        $this->save();
        return $this;
    }
    public static function get($uuid){
        $folders = modelCollection::getInstance('fileFolderModel');
        return $folders->select($folders->id->is($uuid))->fetch();
    }
    public function getHex(){
        $uuid = $this->id->getValue();
        return str_replace(array('-', '{', '}', '(', ')'), '', $uuid);
    }
    public function getBase36(){
        return base_convert($this->getHex(), 16, 36);
    }
    public function getHref(){
        //$f = $this->getResource();
        return app()->rel('folder/'.$this->id);
    }
    public function postInsert(){
        //site::getSelected()->updateFiles();
    }
}
