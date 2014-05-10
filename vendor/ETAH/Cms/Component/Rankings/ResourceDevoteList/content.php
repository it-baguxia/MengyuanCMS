<?php

namespace Etah\Cms\Component\Rankings\ResourceDevoteList;

use Zend\View\Model\ViewModel;
use Zend\Db\Sql\Where;


class Content{
	
	private $serviceManager = null;
	
	function __construct($serviceManager){
		
		$this->serviceManager = $serviceManager;
		
	}
	
	/**
	 * 通过解析内容配置文件 来获取模板中需要的各种数据
	 * 把这部分解析的内容从前台中分离出来，最大的好处就是不需要在网站前台中适应各种内容组件的数据查询方法，
	 * 因为根本不可能适应各种各样的内容组件的方法，那样的话解析配置文件的时候是需要复杂到什么程度的switch 或者  if
	 * 这样的话也很难让第三方的用户进来编辑内容组件，因为每做一个内容组件，都需要在网站前台中解析页面的时候添加一种情况
	 * 
	 * 为了解决以上的问题：
	 * 1.网站前台调用的时候，通过数据库的路径拼接得到内容组件的形成内容的php文件，统一命名为content.php ,文件中的类的名称为  class content{}
	 * 
	 * 2.网站后台调用的时候，通过数据库的路径拼接得到内容组件的形成配置界面的php文件，统一命名为config.php,文件中的类的名称为 class config{}
	 * 
	 * 3.形成配置界面的php文件通过类似于生成添加对象的界面返回给后台的都是一个post的数组，只不过 create界面需要有参数解析然后选择进行各种操作的checkCreate
	 *   配置界面形成的POST数组只需要被序列化，然后存进数据库，然后在形成页面的时候可以被自己解析出来就可以
	 *   
	 * 4.整个过程实际上就是内容组件自己配置自己，自己解析自己的过程，系统完全不参与，系统只负责在合适的时候新建一下内容组件的对象，然后调一下统一对象就可以了
	 * 
	 * 5.内容组件实际上就是提供给用户选择数据来源，存放数据来源，解析数据来源，渲染组件模板，它不停被系统所调用，把形成的字符串返回系统，然后参与构成页面的过程
	 * 
	 * @param object $controller
	 * @param object $contentConfig
	 * @return array data
	 */
	
	private function getDataByParseContentConfig($controller,$contentConfig){
		
		$contentConfig  = unserialize($contentConfig);
		
		//第一步：检验参数
		$this->checkContentConfigParameter($controller,$contentConfig);
		
		//第二步：解析内容,每个内容组件这部分都不一样
		$limit   = $contentConfig['limit'];
		$videoRankingsList = $controller->videoModel->getVideoRankingsList($limit);
		
		//获取用户信息列表
		$userIdList = array();
		$userTempInfoList = array();
		$userInfoList = array();
		foreach ($videoRankingsList as $key=>$record){
			$userIdList[$key] = $record['user_id'];
		}	

		if (sizeof($userIdList) > 0){
			$where = new Where();
			$where->in('id',$userIdList);
			$userTempInfoList = $controller->userModel->getRowByCondition($where,array('id','realname','school_id','photo'));
				
		}

		//获取学校信息列表
		$schoolIdList = array();
		$schoolTempInfoList = array();
		$schoolInfoList = array();
		
		foreach ($userTempInfoList as $userInfo){
			array_push($schoolIdList, $userInfo['school_id']);
		}
		
		if (sizeof($schoolIdList) >0 ){
			$where = new Where();
			$where->in('id',$schoolIdList);
			$schoolTempInfoList = $controller->schoolModel->getRowByCondition($where,array('id','name'));
				
		}

		
		foreach ($schoolTempInfoList as $value){
			$schoolInfoList[$value['id']] = $value['name'];
		}
		
		
		//形成方便列表取用的信息
		foreach ($userTempInfoList as $value){
			
			$userInfoList[$value['id']]['realname'] = $value['realname'];
			$userInfoList[$value['id']]['school_name'] = $schoolInfoList[$value['school_id']];
			$userInfoList[$value['id']]['photo'] = $value['photo'];
			
			
		}
		
		//3.形成冗长的URL, 对文章标题进行限制
		
		$CharacterLimit = $contentConfig['character_limit'];
		
		$basePath = dirname(dirname($controller->getRequest()->getBasePath()));
		
		foreach($videoRankingsList as $key=>$videoRanking){
			
			
			$school_name = (isset($userInfoList[$videoRanking['user_id']]['school_name']))?$userInfoList[$videoRanking['user_id']]['school_name']:'未知学校名';
			$realname = (isset($userInfoList[$videoRanking['user_id']]['realname']))?$userInfoList[$videoRanking['user_id']]['realname']:'佚名';
			$photo = (isset($userInfoList[$videoRanking['user_id']]['photo']))?$userInfoList[$videoRanking['user_id']]['photo']:'';
			
			if ($photo != ''){
				$picPath = $videoRanking['user_id'].'/photo/'.$photo;
			}else{
				$picPath = 'default/photo/defaultface.png';
			}
			$photoUrl =  sprintf("%s/%s/%s",$basePath,'media/user',$picPath);
			
			$videoRankingsList[$key]['school_name'] = $this->SubStrLen($school_name,0,$contentConfig['character_limit'],'UTF-8');
			$videoRankingsList[$key]['realname'] = $realname;
			$videoRankingsList[$key]['photo'] = $photoUrl;
		}
		//4.填装数据
		
		return $videoRankingsList;
		
	}//function formatData() end
	/**
	 * 用于裁剪标题
	 * @param unknown_type $str 字符串本身
	 * @param unknown_type $off 开始位置
	 * @param unknown_type $len 限制长度
	 * @param unknown_type $charset 字符集
	 * @param unknown_type $ellipsis 截取后末尾的字符
	 * @return string
	 */
	private function SubStrLen($str,$off,$len,$charset,$ellipsis="…")
	{
		if(iconv_strlen($str,$charset)>$len){ 
			$str=iconv_substr($str,$off,$len,$charset).$ellipsis; 
		}
		else{
			$str=iconv_substr($str,$off,$len,$charset); 
		}
			 
		return $str; 
	}
	
	
	/**
	 * 用来检验内容组件的内容参数是否符合要求
	 */
	
	private function checkContentConfigParameter($controller,$contentConfig){
		
		$modelList = array('userModel','videoModel','schoolModel');
		
		foreach($modelList as $model){
			
			if(!isset($controller->{$model})){
				die(  sprintf('形成内容组件所需要的数据表%s在当前调用内容组件的控制器没有完成初始化',$model));
			}
		
		}
		
		
	}//function checkContentConfig() end
	
	
	public function render($controller,$contentConfig){
		
		$data = $this->getDataByParseContentConfig($controller,$contentConfig);
		
		$basePath = $controller->getRequest()->getBasePath();
		$PhpRenderer = $this->serviceManager->get ( 'Zend\View\Renderer\PhpRenderer' );
		
		$PhpRenderer->headLink()->appendStylesheet($basePath.'/theme/website/common/Cms/Rankings/ResourceDevoteList/resource_devote.css');
		
		$viewModel = new ViewModel();
		
		$viewModel->setTemplate('component/Rankings/ResourceDevoteList/template');
		
		$viewModel->setVariable('data', $data);
		
		$html = $PhpRenderer->render($viewModel);
		
		return $html;
	}//function renderTemplate() end
	
	
	
	
	
	
	
}//class Common end