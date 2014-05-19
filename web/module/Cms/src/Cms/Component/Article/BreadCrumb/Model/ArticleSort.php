<?php

namespace Cms\Component\Article\BreadCrumb\Model;

use Application\Model\BaseModel;
use Zend\Db\Adapter\Adapter;



class ArticleSort extends BaseModel
{
    protected $table = 'resource_article_sort';
    
    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->initialize();
    }
    
    
}