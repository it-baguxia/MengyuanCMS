<?php

namespace Etah\Cms\Component\Video\Listing\Metallic;

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
	 * 3.形成配置界面的php文件通过类似于生成添加对象的界面返回给后台的都是一个post的数组，只不过create界面需要有参数解析然后选择进行各种操作的checkcreate
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
		//1.查询视频分类的名称
		$videoSortId = $contentConfig['video_sort_id'];
		
		$videoSortColumns = array('name');
		
		$videoSortInfo = $controller->videoSortModel->getRowById($videoSortId,$videoSortColumns);
		
		$videoSortName = $videoSortInfo['name'];
		
		//2.查询视频的列表
		$descendantVideoSortColumns = array('id','name');
		
		$tempDescendantVideoSortIdList = $controller->videoSortModel->getDescendantRowListById($videoSortId,$descendantVideoSortColumns);
		
		$descendantVideoSortIdList = array();
		
		foreach($tempDescendantVideoSortIdList as $videoSort){
			array_push($descendantVideoSortIdList,$videoSort['id']);
		}
		
		
		$where = new where();
		
		$where->in('video_sort_id',$descendantVideoSortIdList);
		
		$where->equalTo('download_status','finished');
		
		$where->equalTo('status','Y');
		
		$where->equalTo('translate_status','Y');
		
		$columns = $contentConfig['columns'];
		
		$limit   = $contentConfig['limit'];
		
		$order = array('id'=>'desc');
		
		$videoList = $controller->videoModel->getRowByCondition($where,$columns,$limit,0,$order);
		
		$userIdList = array();
		$videoIdList = array();
		
		foreach ($videoList as $video){
			
			array_push($videoIdList, $video['id']);
			array_push($userIdList, $video['user_id']);
			
		}
		
		
		//获取用户信息列表
		$userTempInfoList = array();
		$userInfoList = array();

		if (sizeof($userIdList) > 0){
			$where = new Where();
			$where->in('id',$userIdList);
			$userTempInfoList = $controller->userModel->getRowByCondition($where,array('id','realname'));
			foreach ($userTempInfoList as $user){
				$userInfoList[$user['id']] = $user['realname'];
			}
		}
		
		//获取视频时长
		$videoPlayInfo = array();
		$videoPlayTempInfo = array();
		
		if(sizeof($videoIdList) > 0 ){
			$where = new Where();
			$where->in('id',$videoIdList);
			$videoPlayTempInfo = $controller->videoPlayinfoModel->getRowByCondition($where,array('id','length'));
			
			foreach ($videoPlayTempInfo as $video){
				$videoPlayInfo[$video['id']] = $this->formatVideoLength($video['length']);
			}
		}
		
		
		$basePath = dirname(dirname($controller->getRequest()->getBasePath()));
		
		//3.方便模板做出的一些改动，主要是形成冗长的URL
		foreach($videoList as $key=>$video){
			
			$videoList[$key]['title'] = $this->SubStrLen($video['name'],0,$contentConfig['character_limit'],'UTF-8');
			
			$videoList[$key]['realname'] = (isset($video['user_id']) && isset($userInfoList[$video['user_id']]))?$userInfoList[$video['user_id']]:'佚名';
			
			$videoList[$key]['length'] = @$videoPlayInfo[$video['id']];
			
			$filename = sprintf("%s%s/%s_116x87.jpg",VIDEO_DISK_DIRECTORY,$video['id'],$video['thumb']);
				
			if (!file_exists($filename)) {
				$videoList[$key]['img_src']   = sprintf("%s/%s/%s/%s",$basePath,'media/video','default','small.png');
			}else{
				$videoList[$key]['img_src']   = sprintf("%s/%s/%s/%s_116x87.jpg",$basePath,'media/video',$video['id'],$video['thumb']);
			}
			
			$videoList[$key]['title_url'] = $controller->url()->fromRoute('resource',array('controller'=>'video','action'=>'play','id'=>$video['id']));
																							
			$videoList[$key]['img_url']   = $videoList[$key]['title_url'];
		}
		
		
		//4.填装数据
		$data['video_sort_id'] 	 = $videoSortId;
		$data['video_sort_url']  = $controller->url()->fromRoute('website',array('controller'=>'search','id'=>$videoSortId));
																					
		$data['video_sort_name'] = $videoSortName;
		$data['list'] 			 = $videoList;
		
		return $data;
		
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
	
	private function formatVideoLength($sec)
	{
		$hours = floor($sec / 3600);
		$remainSeconds = $sec % 3600;
		$minutes = floor($remainSeconds / 60);
		$seconds = intval($sec - $hours * 3600 - $minutes * 60);
		
		$formatLength = sprintf('%s:%s:%s',$hours>10?$hours:'0'.$hours,$minutes>10?$minutes:'0'.$minutes,$seconds>10?$seconds:'0'.$seconds);
		
		return $formatLength;
	}
	
	
	/**
	 * 用来检验内容组件的内容参数是否符合要求
	 */
	
	private function checkContentConfigParameter($controller,$contentConfig){
		
		$modelList = array('videoModel','videoSortModel','videoPlayinfoModel');
		
		foreach($modelList as $model){
			
			if(!isset($controller->{$model})){
				die(  sprintf('形成内容组件所需要的数据表%s在当前调用内容组件的控制器没有完成初始化',$model));
			}
		
		}
		
	}//function checkContentConfig() end
	
	public function render($controller,$contentConfig){
		
		$data = $this->getDataByParseContentConfig($controller,$contentConfig);
		
		$PhpRenderer = $this->serviceManager->get ( 'Zend\View\Renderer\PhpRenderer' );
		
		$basePath = $controller->getRequest()->getBasePath();
		$PhpRenderer->headLink()->appendStylesheet($basePath.'/theme/website/common/Cms/Common/index/lesson_navigate.css');
		$PhpRenderer->headScript()->appendFile($basePath.'/js/cms/video/listing/metallic/lesson_navigate.js');
		$viewModel = new ViewModel();
		
		$viewModel->setTemplate('Component/Video/Listing/Metallic/template');
		
		$viewModel->setVariable('data', $data);
		
		$html = $PhpRenderer->render($viewModel);
		
		return $html;
	}//function renderTemplate() end
	
	
	
	
	
	
	
}//class Common end